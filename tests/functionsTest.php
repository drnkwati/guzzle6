<?php
namespace GuzzleHttp\Guzzle\Test;

use GuzzleHttp\Guzzle;

class FunctionsTest extends \PHPUnit_Framework_TestCase
{
    public function testExpandsTemplate()
    {
        $this->assertEquals(
            'foo/123',
            \GuzzleHttp\Guzzle\uri_template('foo/{bar}', array('bar' => '123'))
        );
    }
    public function noBodyProvider()
    {
        return array(array('get'), array('head'), array('delete'));
    }

    public function testProvidesDefaultUserAgent()
    {
        $ua = \GuzzleHttp\Guzzle\default_user_agent();
        $this->assertEquals(1, preg_match('#^ehough/guzzle/[1-9]+\.[0-9]+\.[0-9]+ curl/.+ PHP/.+$#', $ua));
    }

    public function typeProvider()
    {
        return array(
            array('foo', 'string(3) "foo"'),
            array(true, 'bool(true)'),
            array(false, 'bool(false)'),
            array(10, 'int(10)'),
            array(1.0, 'float(1)'),
            array(new StrClass(), 'object(GuzzleHttp\Guzzle\Test\StrClass)'),
            array(array('foo'), 'array(1)')
        );
    }
    /**
     * @dataProvider typeProvider
     */
    public function testDescribesType($input, $output)
    {
        $this->assertEquals($output, \GuzzleHttp\Guzzle\describe_type($input));
    }

    public function testParsesHeadersFromLines()
    {
        $lines = array('Foo: bar', 'Foo: baz', 'Abc: 123', 'Def: a, b');
        $this->assertEquals(array(
            'Foo' => array('bar', 'baz'),
            'Abc' => array('123'),
            'Def' => array('a, b'),
        ), \GuzzleHttp\Guzzle\headers_from_lines($lines));
    }

    public function testParsesHeadersFromLinesWithMultipleLines()
    {
        $lines = array('Foo: bar', 'Foo: baz', 'Foo: 123');
        $this->assertEquals(array(
            'Foo' => array('bar', 'baz', '123'),
        ), \GuzzleHttp\Guzzle\headers_from_lines($lines));
    }

    public function testReturnsDebugResource()
    {
        $this->assertTrue(is_resource(\GuzzleHttp\Guzzle\debug_resource()));
    }

    public function testProvidesDefaultCaBundler()
    {
        $this->assertFileExists(\GuzzleHttp\Guzzle\default_ca_bundle());
    }

    public function noProxyProvider()
    {
        return array(
            array('mit.edu', array('.mit.edu'), false),
            array('foo.mit.edu', array('.mit.edu'), true),
            array('mit.edu', array('mit.edu'), true),
            array('mit.edu', array('baz', 'mit.edu'), true),
            array('mit.edu', array('', '', 'mit.edu'), true),
            array('mit.edu', array('baz', '*'), true),
        );
    }

    /**
     * @dataProvider noproxyProvider
     */
    public function testChecksNoProxyList($host, $list, $result)
    {
        $this->assertSame(
            $result,
            \GuzzleHttp\Guzzle\is_host_in_noproxy($host, $list)
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testEnsuresNoProxyCheckHostIsSet()
    {
        \GuzzleHttp\Guzzle\is_host_in_noproxy('', array());
    }

    public function testEncodesJson()
    {
        $this->assertEquals('true', \GuzzleHttp\Guzzle\json_encode(true));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testEncodesJsonAndThrowsOnError()
    {
        \GuzzleHttp\Guzzle\json_encode("\x99");
    }

    public function testDecodesJson()
    {
        $this->assertSame(true, \GuzzleHttp\Guzzle\json_decode('true'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testDecodesJsonAndThrowsOnError()
    {
        \GuzzleHttp\Guzzle\json_decode('{{]]');
    }
}

final class StrClass
{
    public function __toString()
    {
        return 'foo';
    }
}
