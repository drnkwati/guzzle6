<?php
namespace GuzzleHttp\Test\Guzzle\Handler;

use GuzzleHttp\Guzzle\Handler\MockHandler;
use GuzzleHttp\Guzzle\Handler\Proxy;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Guzzle\RequestOptions;

/**
 * @covers \GuzzleHttp\Guzzle\Handler\Proxy
 */
class ProxyTest extends \PHPUnit_Framework_TestCase
{
    public function testSendsToNonSync()
    {
        $a = $b = null;
        $m1 = new MockHandler(array(function ($v) use (&$a) { $a = $v; }));
        $m2 = new MockHandler(array(function ($v) use (&$b) { $b = $v; }));
        $h = Proxy::wrapSync($m1, $m2);
        call_user_func($h, new Request('GET', 'http://foo.com'), array());
        $this->assertNotNull($a);
        $this->assertNull($b);
    }

    public function testSendsToSync()
    {
        $a = $b = null;
        $m1 = new MockHandler(array(function ($v) use (&$a) { $a = $v; }));
        $m2 = new MockHandler(array(function ($v) use (&$b) { $b = $v; }));
        $h = Proxy::wrapSync($m1, $m2);
        call_user_func($h, new Request('GET', 'http://foo.com'), array(RequestOptions::SYNCHRONOUS => true));
        $this->assertNull($a);
        $this->assertNotNull($b);
    }

    public function testSendsToStreaming()
    {
        $a = $b = null;
        $m1 = new MockHandler(array(function ($v) use (&$a) { $a = $v; }));
        $m2 = new MockHandler(array(function ($v) use (&$b) { $b = $v; }));
        $h = Proxy::wrapStreaming($m1, $m2);
        call_user_func($h, new Request('GET', 'http://foo.com'), array());
        $this->assertNotNull($a);
        $this->assertNull($b);
    }

    public function testSendsToNonStreaming()
    {
        $a = $b = null;
        $m1 = new MockHandler(array(function ($v) use (&$a) { $a = $v; }));
        $m2 = new MockHandler(array(function ($v) use (&$b) { $b = $v; }));
        $h = Proxy::wrapStreaming($m1, $m2);
        call_user_func($h, new Request('GET', 'http://foo.com'), array('stream' => true));
        $this->assertNull($a);
        $this->assertNotNull($b);
    }
}
