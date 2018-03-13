<?php
namespace GuzzleHttp\Test\Guzzle\Handler;

use GuzzleHttp\Guzzle\Handler\EasyHandle;
use GuzzleHttp\Psr7;

/**
 * @covers \GuzzleHttp\Guzzle\Handler\EasyHandle
 */
class EasyHandleTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage The EasyHandle has been released
     */
    public function testEnsuresHandleExists()
    {
        $easy = new EasyHandle;
        unset($easy->handle);
        $easy->handle;
    }
}
