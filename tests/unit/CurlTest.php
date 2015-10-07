<?php
namespace tests\unit;

use libs\Curl;

class CurlTest extends \PHPUnit_Framework_TestCase
{
    private $curl;

    public function setUp()
    {
        $this->curl = new Curl();
        $this->curl->useDefaultUserAgent();
        $this->curl->followLocation();
        $this->curl->ignoreSSL();
        $this->curl->setTimeout(120);
    }

    public function testGetEffectiveUrl()
    {
        //Set url with redirect
        $this->curl->setUrl('http://motocms.com');
        $this->curl->exec();
        $this->assertEquals('http://www.motocms.com/', $this->curl->getEffectiveUrl());
    }

    public function testGetHttpCode()
    {
        //Set dummy not exist url
        $this->curl->setUrl('http://www.motocms.com/dsdsds21211221212121');
        $this->curl->exec();
        $this->assertEquals($this->curl->getHttpCode(), 404);
    }
}