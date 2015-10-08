<?php
namespace libs;

class LinkProcessor
{
    private $curl;
    private $htmlDom;
    private $parsedLinks;
    private $parserImagesLinks;
    private $xmlWriter;
    private $filterArray;
    private $baseUrl;
    private $generatingStarted;
    private $fileName;
    private $primaryUrl;

    public function __construct($primaryUrl, $baseUrl = null)
    {
        $this->parsedLinks = array();
        $this->parserImagesLinks = array();

        //Initializing SimpleHtmlDom
        $this->htmlDom = new simple_html_dom();

        //Initializing CURL
        $this->curl = new Curl();
        $this->curl->useDefaultUserAgent();
        $this->curl->followLocation();
        $this->curl->ignoreSSL();
        $this->curl->setTimeout(120);

        //Generate temporary file name
        $randName = RandomHelper::generateString() . '.xml';
        $fileName = TEMP_DIR . $randName;
        $this->fileName = $fileName;

        //Initializing XML writer
        $this->xmlWriter = new \XMLWriter();
        $this->xmlWriter->openURI($fileName);
        $this->xmlWriter->startDocument('1.0', 'UTF-8');
        $this->xmlWriter->setIndentString(str_repeat(' ', 3));
        $this->xmlWriter->setIndent(true);

        $this->baseUrl = $baseUrl;
        $this->generatingStarted = false;
        $this->primaryUrl = $primaryUrl;
    }

    public function getFilters()
    {
        return $this->filterArray;
    }

    public function setFilter(array $filter)
    {
        $this->filterArray = $filter;
    }

    private function isLinkContains($link)
    {
        if (!isset($this->filterArray['contains']) || count($this->filterArray['excludes']) < 1) {
            return true;
        }

        return $this->arrayContainsByRegex($link, $this->filterArray['contains']);
    }

    private function isLinkNotContains($link)
    {
        if (!isset($this->filterArray['excludes']) || count($this->filterArray['excludes']) < 1) {
            return true;
        }

        return !$this->arrayContainsByRegex($link, $this->filterArray['excludes']);
    }

    private function arrayContainsByRegex($link, $filters)
    {
        foreach ($filters as $filter) {
            if (preg_match('/' . $filter . '/', $link)) {
                return true;
            }
        }

        return false;
    }

    public function generateSitemap($isImageSitemap = false)
    {
        $this->generatingStarted = true;
        $this->xmlWriter->startElementNS(null, 'urlset', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $this->getLinks($this->primaryUrl, $isImageSitemap);
        $this->xmlWriter->endElement();

        if (count($this->parsedLinks) > 0) {
            return true;
        }

        return false;
    }

    private function hasHttpProtocol($link)
    {
        if (!preg_match("~^https?://~i", $link)) {
            return false;
        }

        return true;
    }

    public function getLinks($link, $isImageSitemap = false)
    {
        //Clear HTML DOM object 'cause when we use recursion we can get memory leak
        $this->htmlDom->clear();
        $filteredUrl = $link;

        //If link without protocol trying to add it and then validate
        if (!$this->hasHttpProtocol($filteredUrl)) {
            $filteredUrl = "http://" . $filteredUrl;

            if (filter_var($filteredUrl, FILTER_VALIDATE_URL) === false) {
                return false;
            }
        }
        $this->curl->setUrl($filteredUrl);
        $rawHtml = $this->curl->exec();
        $httpCode = $this->curl->getHttpCode();

        if ($httpCode == 404 || $httpCode == 503) {
            return false;
        }

        //If curl has errors
        if ($rawHtml === false) {
            return false;
        }
        $effectiveUrl = $this->curl->getEffectiveUrl();

        // Check if we've already parsed link
        if ($this->isLinkProcessed($effectiveUrl, $this->parsedLinks)) {
            return false;
        }
        $this->parsedLinks[] = $effectiveUrl;

        if (!$isImageSitemap) {
            $this->writeUrlToSitemap($effectiveUrl);
        }

        $this->htmlDom->load($rawHtml);

        if ($isImageSitemap) {
            foreach ($this->htmlDom->find('img') as $img) {
                $foundImg = $img->src;
                $sanitizedUrl = $this->sanitizeUrl($foundImg);

                if (!$sanitizedUrl) {
                    continue;
                }

                // Check if we've already parsed link
                if ($this->isLinkProcessed($sanitizedUrl, $this->parserImagesLinks)) {
                    continue;
                }
                $this->parserImagesLinks[] = $sanitizedUrl;
                $this->writeUrlToSitemap($sanitizedUrl);
            }
        }

        foreach ($this->htmlDom->find('a') as $element) {
            $foundLink = $element->href;
            $sanitizedUrl = $this->sanitizeUrl($foundLink);

            if (!$sanitizedUrl) {
                continue;
            }
            $this->getLinks($sanitizedUrl, $isImageSitemap);
        }

    }

    private function sanitizeUrl($url)
    {
        if (null !== $this->baseUrl &&
            isset($url[0]) && $url[0] == '/' && preg_match('/\/\S*\//', $url)
        ) {
            $baseLength = strlen($this->baseUrl);

            if ($this->baseUrl[$baseLength - 1] == '/') {
                $resultBaseUrl = substr($this->baseUrl, 0, $baseLength - 1);
            } else {
                $resultBaseUrl = $this->baseUrl;
            }
            $url = $resultBaseUrl . $url;
        }

        if ($url === '/' || !$this->isLinkContains($url) || !$this->isLinkNotContains($url)) {
            return false;
        }

        //Find hash position and substr it
        $hashPosition = strpos($url, '#');

        if ($hashPosition !== false) {
            $url = substr($url, 0, $hashPosition);

            //If link with hash only - ignore it
            if (empty($url)) {
                return false;
            }
        }

        return $url;
    }

    private function writeUrlToSitemap($urlForSitemap)
    {
        $this->xmlWriter->startElement('url');
        $this->xmlWriter->writeElement("loc", $urlForSitemap);
        $this->xmlWriter->endElement();
    }

    public function save($path)
    {
        if (!$this->generatingStarted) {
            throw new \Exception('Cant save file before sitemap generating');
        }

        if (!$this->validateXmlFile($this->fileName)) {
            throw new \Exception('Invalid xml file');
        }
        $this->xmlWriter->flush();
        unset($this->xmlWriter);
        rename($this->fileName, $path);
    }

    private function validateXmlFile($filePath)
    {
        if (!file_exists($filePath)) {
            return false;
        }
        $xml = \XMLReader::open($filePath);
        $xml->setParserProperty(\XMLReader::VALIDATE, true);

        return $xml->isValid();
    }

    private function checkLinksEqual($url1, $url2)
    {
        $normalizedUrl1 = $this->normalizeUrl($url1);
        $normalizedUrl2 = $this->normalizeUrl($url2);

        return $normalizedUrl1 == $normalizedUrl2;
    }

    private function normalizeUrl($url)
    {
        $processedUrl = parse_url($url);

        if (isset($processedUrl['scheme'])
            && isset($processedUrl['host'])
            && isset($processedUrl['path'])
        ) {
            if (strpos($processedUrl['host'], 'www.') === false) {
                $processedUrl['host'] = 'www.' . $processedUrl['host'];
            }
            $pathLength = strlen($processedUrl['path']);

            if ($pathLength > 0 && $processedUrl['path'][$pathLength - 1] == '/') {
                $processedUrl['path'] = substr($processedUrl['path'], 0, $pathLength - 1);
            }

            return $processedUrl['scheme'] . '://' . $processedUrl['host'] . $processedUrl['path'];
        }

        return $url;
    }

    private function isLinkProcessed($link, $parsedLinksArray)
    {
        if (count($parsedLinksArray) < 1) {
            return false;
        }

        foreach ($parsedLinksArray as $parsedLink) {
            if ($this->checkLinksEqual($link, $parsedLink)) {
                return true;
            }
        }

        return false;
    }
}
