<?php
namespace tests\unit;

use libs;

class LinkProcessorTest extends \PHPUnit_Framework_TestCase
{
    private $linkProcessor;

    public function setUp()
    {
        $testUrl = 'testurl';
        $this->linkProcessor = new \libs\LinkProcessor($testUrl);
    }

    public function testIsLinkContains()
    {
        $filterArray = array(
            'contains' => array(
                '\/es\/'
            ),
            'excludes' => array(
                '\/pl\/'
            )
        );

        $this->linkProcessor->setFilter($filterArray);
        $method = $this->getPrivateMethod('\libs\LinkProcessor', 'isLinkContains');

        $result = $method->invokeArgs(
            $this->linkProcessor,
            array('http://motocms.com/es/faq/')
        );

        $this->assertTrue($result);
    }

    public function testIsLinkNotContains()
    {
        $filterArray = array(
            'contains' => array(
                '\/es\/'
            ),
            'excludes' => array(
                '\/pl\/'
            )
        );

        $this->linkProcessor->setFilter($filterArray);
        $method = $this->getPrivateMethod('\libs\LinkProcessor', 'isLinkNotContains');

        $result = $method->invokeArgs(
            $this->linkProcessor,
            array('http://motocms.com/es/faq/')
        );

        $this->assertTrue($result);
    }

    public function testCheckLinksEqual()
    {
        $method = $this->getPrivateMethod('\libs\LinkProcessor', 'checkLinksEqual');
        $url1 = 'http://www.motocms.com/es/privacy';
        $url2 = 'http://motocms.com/es/privacy/';

        $result = $method->invokeArgs(
            $this->linkProcessor,
            array($url1, $url2)
        );

        $this->assertTrue($result);
    }

    /**
     * TODO: move this to other parent class
     * @param $className
     * @param $methodName
     * @return mixed
     */
    private function getPrivateMethod($className, $methodName)
    {
        $reflector = new \ReflectionClass($className);
        $method = $reflector->getMethod($methodName);
        $method->setAccessible(true);

        return $method;
    }
}
