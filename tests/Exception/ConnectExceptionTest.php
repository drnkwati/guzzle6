<?php
namespace GuzzleHttp\Tests\Event;

use GuzzleHttp\Guzzle\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;

/**
 * @covers \GuzzleHttp\Guzzle\Exception\ConnectException
 */
class ConnectExceptionTest extends \PHPUnit_Framework_TestCase
{
    public function testHasNoResponse()
    {
        $req = new Request('GET', '/');
        $prev = new \Exception();
        $e = new ConnectException('foo', $req, $prev, array('foo' => 'bar'));
        $this->assertSame($req, $e->getRequest());
        $this->assertNull($e->getResponse());
        $this->assertFalse($e->hasResponse());
        $this->assertEquals('foo', $e->getMessage());
        $ctx = $e->getHandlerContext();
        $this->assertEquals('bar', $ctx['foo']);
        $this->assertSame($prev, $e->getPrevious());
    }
}
