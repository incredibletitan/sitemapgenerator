<?php
namespace libs;

use models\LinkModel;

class LinkProcessor
{
    private $curl;
    private $htmlDom;
    private $parsedLinks;
    private $xmlWriter;
    private $filterArray;
    private $baseUrl;
    private $primaryUrlId;
    private $linkExists;

    public function __construct($filePath, $primaryUrl, $baseUrl = null)
    {
        $this->parsedLinks = array();

        //Initializing SimpleHtmlDom
        $this->htmlDom = new simple_html_dom();

        //Initializing CURL
        $this->curl = new Curl();
        $this->curl->useDefaultUserAgent();
        $this->curl->followLocation();
        $this->curl->ignoreSSL();
        $this->curl->setTimeout(120);

        //Initializing XML writer
        $this->xmlWriter = new \XMLWriter();
        $this->xmlWriter->openURI($filePath);
        $this->xmlWriter->startDocument('1.0', 'UTF-8');
        $this->xmlWriter->setIndentString(str_repeat(' ', 3));
        $this->xmlWriter->setIndent(true);

        $this->baseUrl = $baseUrl;
        $this->primaryUrl = trim($primaryUrl);
        $this->linkModel = new \models\LinkModel();
        $oldParsedLinkId = $this->linkModel->checkPrimaryUrlAlreadyParsed($this->primaryUrl);

        if ($oldParsedLinkId) {
            $this->primaryUrlId = $oldParsedLinkId;

            //Set flag true to indicate that link already exist in database
            $this->linkExists = true;
        } else {
            $lastInsertedId = $this->linkModel->addPrimaryUrlToDB($this->primaryUrl);

            if (!$lastInsertedId) {
                throw new \Exception('No data were inserted');
            }
            $this->primaryUrlId = $lastInsertedId;
            $this->linkExists = false;
        }
        $this->linkModel->addUrlToDB('test.com', $this->primaryUrlId);
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

    public function generateSitemap($maxDepth = 3)
    {
        $this->xmlWriter->startElementNS(null, 'urlset', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        //Write source url to sitemap
        $this->xmlWriter->startElement('url');
        $this->xmlWriter->writeElement("loc", $this->primaryUrl);
        $this->xmlWriter->endElement();
        $this->getLinks($this->primaryUrl, 0, $maxDepth);
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

    public function getLinks($link, $depth, $maxDepth = 5)
    {
        if ($depth <= $maxDepth) {
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
            $parsedSourceLink = parse_url($filteredUrl);
            $this->curl->setUrl($filteredUrl);
            $rawHtml = $this->curl->exec();

            //If curl has errors
            if ($rawHtml === false) {
                return false;
            }
            $this->htmlDom->load($rawHtml);

            foreach ($this->htmlDom->find('a') as $element) {
                $foundLink = $element->href;

                if (null !==  $this->baseUrl &&
                    isset($foundLink[0]) && $foundLink[0] == '/' && preg_match('/\/\S*\//', $foundLink)) {
                    $baseLength = strlen($this->baseUrl);

                    if ($this->baseUrl[$baseLength - 1] == '/') {
                        $resultBaseUrl = substr($this->baseUrl, 0, $baseLength - 1);
                    } else {
                        $resultBaseUrl = $this->baseUrl;
                    }
                    $foundLink = $resultBaseUrl . $foundLink;
                }

                if ($foundLink === '/' || !$this->isLinkContains($foundLink) || !$this->isLinkNotContains($foundLink)) {
                    continue;
                }

                //Find hash position and substr it
                $hashPosition = strpos($foundLink, '#');

                if ($hashPosition !== false) {
                    $foundLink = substr($foundLink, 0, $hashPosition);

                    //If link with hash only - ignore it
                    if (empty($foundLink)) {
                        continue;
                    }
                }
                $parsedUrl = parse_url($foundLink);

                if (!isset($parsedUrl['host']) && !empty($parsedUrl['path'])) {
                    $resultUrl = $parsedSourceLink['host'] . $parsedUrl['path'];
                } else {
                    $resultUrl = $foundLink;
                }

                // Check if we've already parsed link
                if ($this->isLinkProcessed($resultUrl)) {
                    continue;
                }

                if (!$this->hasHttpProtocol($resultUrl)) {
                    $urlForSitemap = $parsedSourceLink['scheme'] . "://" . $resultUrl;
                } else {
                    $urlForSitemap = $resultUrl;
                }

                $this->parsedLinks[] = $resultUrl;
                $this->xmlWriter->startElement('url');
                $this->xmlWriter->writeElement("loc", $urlForSitemap);
                $this->xmlWriter->endElement();
                $this->getLinks($resultUrl, $depth + 1, $maxDepth);
            }
        }
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
            && isset($processedUrl['path'])) {
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


    private function isLinkProcessed($link)
    {
        if (count($this->parsedLinks) < 1) {
            return false;
        }

        foreach ($this->parsedLinks as $parsedLink) {
            if ($this->checkLinksEqual($link, $parsedLink)) {
                return true;
            }
        }

        return false;
    }
}
