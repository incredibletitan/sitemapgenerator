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

    public function testIsLinkInFilter()
    {
        $filterArray = array('motocms\.com\/[^es|pl|ru|de]');
        $this->linkProcessor->setFilter($filterArray);
        $method = $this->getPrivateMethod('\libs\LinkProcessor', 'isLinkInFilter');

        $result = $method->invokeArgs(
            $this->linkProcessor,
            array('http://www.motocms.com/website-templates/motocms-html-template/54920.html')
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
