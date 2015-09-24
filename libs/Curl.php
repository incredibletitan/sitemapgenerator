<?php

class Curl
{
    private $curl;
    private $defaultUserAgent;
    const DEFAULT_USER_AGENT = 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322)';

    public function __construct()
    {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_HEADER, 0);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
    }

    public function __destruct()
    {
        curl_close($this->curl);
    }

    public function setUrl($url)
    {
        curl_setopt($this->curl, CURLOPT_URL, $url);
    }

    /**
     * Ignoring SSL verification
     */
    public function ignoreSSL()
    {
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
    }

    public function setProxy($proxy)
    {
        curl_setopt($this->curl, CURLOPT_PROXY, $proxy);
    }

    public function setUserAgent($agent)
    {
        curl_setopt($this->curl, CURLOPT_USERAGENT, $agent);
    }

    public function useDefaultUserAgent()
    {
        $this->setUserAgent(self::DEFAULT_USER_AGENT);
    }

    public function followLocation()
    {
        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true);
    }

    public function exec()
    {
        $result = curl_exec($this->curl);

        return $result;
    }

    public function setTimeout($timeout)
    {
        curl_setopt($this->curl, CURLOPT_TIMEOUT, $timeout);
    }
}
