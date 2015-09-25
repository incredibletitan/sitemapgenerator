<?php
namespace libs;

class LinkProcessor
{
    private $curl;
    private $htmlDom;
    private $parsedLinks;
    private $xmlWriter;
    private $filterArray;

    public function __construct($filePath)
    {
        $this->parsedLinks = array();

        //Initializing SimpleHtmlDom
        $this->htmlDom = new simple_html_dom();

        //Initializing CURL
        $this->curl = new Curl();
        $this->curl->useDefaultUserAgent();
        $this->curl->followLocation();
        $this->curl->ignoreSSL();
        $this->curl->setTimeout(30);

        //Initializing XML writer
        $this->xmlWriter = new \XMLWriter();
        $this->xmlWriter->openURI($filePath);
        $this->xmlWriter->startDocument('1.0', 'UTF-8');
        $this->xmlWriter->setIndentString(str_repeat(' ', 3));
        $this->xmlWriter->setIndent(true);
    }

    public function getFilters()
    {
        return $this->filterArray;
    }

    public function setFilter(array $filter)
    {
        $this->filterArray = $filter;
    }

    private function isLinkInFilter($link)
    {
        foreach ($this->filterArray as $filter) {
            if (preg_match('/' . $filter . '/', $link)) {
                return true;
            }
        }

        return false;
    }

    public function generateSitemap($link, $maxDepth = 3)
    {
        $this->xmlWriter->startElementNS(null, 'urlset', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        //Write source url to sitemap
        $this->xmlWriter->startElement('url');
        $this->xmlWriter->writeElement("loc", $link);
        $this->xmlWriter->endElement();
        $this->getLinks($link, 0, $maxDepth);
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

    public function getLinks($link, $depth, $maxDepth = 3)
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

                //Ignore root and links in filter
                if ($foundLink === '/' || $this->isLinkInFilter($foundLink)) {
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

                if ((isset($parsedUrl['host']) && !empty($parsedUrl['host'])
                        && ($parsedUrl['host'] !== $parsedSourceLink['host']))
                    || (!isset($parsedUrl['host']) || empty($parsedUrl['host']))
                    && (!isset($parsedUrl['path']) || empty($parsedUrl['path']))) {
                    continue;
                } else {
                    if (!isset($parsedUrl['host']) && !empty($parsedUrl['path'])) {
                        $resultUrl = $parsedSourceLink['host'] . $parsedUrl['path'];
                    } else {
                        $resultUrl = $foundLink;
                    }
                }

                // Check if we've already parsed link
                if (in_array($resultUrl, $this->parsedLinks)) {
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
                $this->getLinks($resultUrl, $depth + 1);
            }
        }
    }
}
