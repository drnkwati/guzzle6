<?php
namespace GuzzleHttp\Test\Guzzle\Handler;

use GuzzleHttp\Guzzle\Handler\CurlFactory;
use GuzzleHttp\Guzzle\Handler\EasyHandle;
use GuzzleHttp\Guzzle\Test\Server;
use GuzzleHttp\Guzzle\Handler;
use GuzzleHttp\Psr7;
use GuzzleHttp\Guzzle\TransferStats;
use Psr\Http\Message\ResponseInterface;

/**
 * @covers \GuzzleHttp\Guzzle\Handler\CurlFactory
 */
class CurlFactoryTest extends \PHPUnit_Framework_TestCase
{
    public static function setUpBeforeClass()
    {
        $_SERVER['curl_test'] = true;
        unset($_SERVER['_curl']);
    }

    public static function tearDownAfterClass()
    {
        unset($_SERVER['_curl'], $_SERVER['curl_test']);
    }

    public function testCreatesCurlHandle()
    {
        Server::flush();
        Server::enqueue(array(
            new Psr7\Response(200, array(
                'Foo' => 'Bar',
                'Baz' => 'bam',
                'Content-Length' => 2,
            ), 'hi')
        ));
        $stream = Psr7\stream_for();
        $request = new Psr7\Request('PUT', Server::$url, array(
            'Hi'             => ' 123',
            'Content-Length' => '7'
        ), 'testing');
        $f = new Handler\CurlFactory(3);
        $result = $f->create($request, array('sink' => $stream));
        $this->assertInstanceOf('\GuzzleHttp\Guzzle\Handler\EasyHandle', $result);
        $this->assertInternalType('resource', $result->handle);
        $this->assertInternalType('array', $result->headers);
        $this->assertSame($stream, $result->sink);
        curl_close($result->handle);
        $this->assertEquals('PUT', $_SERVER['_curl'][CURLOPT_CUSTOMREQUEST]);
        $this->assertEquals(
            'http://127.0.0.1:8126/',
            $_SERVER['_curl'][CURLOPT_URL]
        );
        // Sends via post fields when the request is small enough
        $this->assertEquals('testing', $_SERVER['_curl'][CURLOPT_POSTFIELDS]);
        $this->assertEquals(0, $_SERVER['_curl'][CURLOPT_RETURNTRANSFER]);
        $this->assertEquals(0, $_SERVER['_curl'][CURLOPT_HEADER]);
        $this->assertEquals(150, $_SERVER['_curl'][CURLOPT_CONNECTTIMEOUT]);
        $this->assertInstanceOf('Closure', $_SERVER['_curl'][CURLOPT_HEADERFUNCTION]);
        if (defined('CURLOPT_PROTOCOLS')) {
            $this->assertEquals(
                CURLPROTO_HTTP | CURLPROTO_HTTPS,
                $_SERVER['_curl'][CURLOPT_PROTOCOLS]
            );
        }
        $this->assertContains('Expect:', $_SERVER['_curl'][CURLOPT_HTTPHEADER]);
        $this->assertContains('Accept:', $_SERVER['_curl'][CURLOPT_HTTPHEADER]);
        $this->assertContains('Content-Type:', $_SERVER['_curl'][CURLOPT_HTTPHEADER]);
        $this->assertContains('Hi: 123', $_SERVER['_curl'][CURLOPT_HTTPHEADER]);
        $this->assertContains('Host: 127.0.0.1:8126', $_SERVER['_curl'][CURLOPT_HTTPHEADER]);
    }

    public function testSendsHeadRequests()
    {
        Server::flush();
        Server::enqueue(array(new Psr7\Response()));
        $a = new Handler\CurlMultiHandler();
        $response = call_user_func($a, new Psr7\Request('HEAD', Server::$url), array());
        $response->wait();
        $this->assertEquals(true, $_SERVER['_curl'][CURLOPT_NOBODY]);
        $checks = array(CURLOPT_WRITEFUNCTION, CURLOPT_READFUNCTION, CURLOPT_INFILE);
        foreach ($checks as $check) {
            $this->assertArrayNotHasKey($check, $_SERVER['_curl']);
        }
        $received = Server::received();
        $this->assertEquals('HEAD', $received[0]->getMethod());
    }

    public function testCanAddCustomCurlOptions()
    {
        Server::flush();
        Server::enqueue(array(new Psr7\Response()));
        $a = new Handler\CurlMultiHandler();
        $req = new Psr7\Request('GET', Server::$url);
        call_user_func($a, $req, array('curl' => array(CURLOPT_LOW_SPEED_LIMIT => 10)));
        $this->assertEquals(10, $_SERVER['_curl'][CURLOPT_LOW_SPEED_LIMIT]);
    }

    public function testCanChangeCurlOptions()
    {
        Server::flush();
        Server::enqueue(array(new Psr7\Response()));
        $a = new Handler\CurlMultiHandler();
        $req = new Psr7\Request('GET', Server::$url);
        call_user_func($a, $req, array('curl' => array(CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0)));
        $this->assertEquals(CURL_HTTP_VERSION_1_0, $_SERVER['_curl'][CURLOPT_HTTP_VERSION]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage SSL CA bundle not found: /does/not/exist
     */
    public function testValidatesVerify()
    {
        $f = new Handler\CurlFactory(3);
        $f->create(new Psr7\Request('GET', Server::$url), array('verify' => '/does/not/exist'));
    }

    public function testCanSetVerifyToFile()
    {
        $f = new Handler\CurlFactory(3);
        $f->create(new Psr7\Request('GET', 'http://foo.com'), array('verify' => __FILE__));
        $this->assertEquals(__FILE__, $_SERVER['_curl'][CURLOPT_CAINFO]);
        $this->assertEquals(2, $_SERVER['_curl'][CURLOPT_SSL_VERIFYHOST]);
        $this->assertEquals(true, $_SERVER['_curl'][CURLOPT_SSL_VERIFYPEER]);
    }

    public function testAddsVerifyAsTrue()
    {
        $f = new Handler\CurlFactory(3);
        $f->create(new Psr7\Request('GET', Server::$url), array('verify' => true));
        $this->assertEquals(2, $_SERVER['_curl'][CURLOPT_SSL_VERIFYHOST]);
        $this->assertEquals(true, $_SERVER['_curl'][CURLOPT_SSL_VERIFYPEER]);
        $this->assertArrayNotHasKey(CURLOPT_CAINFO, $_SERVER['_curl']);
    }

    public function testCanDisableVerify()
    {
        $f = new Handler\CurlFactory(3);
        $f->create(new Psr7\Request('GET', Server::$url), array('verify' => false));
        $this->assertEquals(0, $_SERVER['_curl'][CURLOPT_SSL_VERIFYHOST]);
        $this->assertEquals(false, $_SERVER['_curl'][CURLOPT_SSL_VERIFYPEER]);
    }

    public function testAddsProxy()
    {
        $f = new Handler\CurlFactory(3);
        $f->create(new Psr7\Request('GET', Server::$url), array('proxy' => 'http://bar.com'));
        $this->assertEquals('http://bar.com', $_SERVER['_curl'][CURLOPT_PROXY]);
    }

    public function testAddsViaScheme()
    {
        $f = new Handler\CurlFactory(3);
        $f->create(new Psr7\Request('GET', Server::$url), array(
            'proxy' => array('http' => 'http://bar.com', 'https' => 'https://t'),
        ));
        $this->assertEquals('http://bar.com', $_SERVER['_curl'][CURLOPT_PROXY]);
        $this->checkNoProxyForHost('http://test.test.com', array('test.test.com'), false);
        $this->checkNoProxyForHost('http://test.test.com', array('.test.com'), false);
        $this->checkNoProxyForHost('http://test.test.com', array('*.test.com'), true);
        $this->checkNoProxyForHost('http://test.test.com', array('*'), false);
        $this->checkNoProxyForHost('http://127.0.0.1', array('127.0.0.*'), true);
    }

    private function checkNoProxyForHost($url, $noProxy, $assertUseProxy)
    {
        $f = new Handler\CurlFactory(3);
        $f->create(new Psr7\Request('GET', $url), array(
            'proxy' => array(
                'http' => 'http://bar.com',
                'https' => 'https://t',
                'no' => $noProxy
            ),
        ));
        if ($assertUseProxy) {
            $this->assertArrayHasKey(CURLOPT_PROXY, $_SERVER['_curl']);
        } else {
            $this->assertArrayNotHasKey(CURLOPT_PROXY, $_SERVER['_curl']);
        }
    }


    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage SSL private key not found: /does/not/exist
     */
    public function testValidatesSslKey()
    {
        $f = new Handler\CurlFactory(3);
        $f->create(new Psr7\Request('GET', Server::$url), array('ssl_key' => '/does/not/exist'));
    }

    public function testAddsSslKey()
    {
        $f = new Handler\CurlFactory(3);
        $f->create(new Psr7\Request('GET', Server::$url), array('ssl_key' => __FILE__));
        $this->assertEquals(__FILE__, $_SERVER['_curl'][CURLOPT_SSLKEY]);
    }

    public function testAddsSslKeyWithPassword()
    {
        $f = new Handler\CurlFactory(3);
        $f->create(new Psr7\Request('GET', Server::$url), array('ssl_key' => array(__FILE__, 'test')));
        $this->assertEquals(__FILE__, $_SERVER['_curl'][CURLOPT_SSLKEY]);
        $this->assertEquals('test', $_SERVER['_curl'][CURLOPT_SSLKEYPASSWD]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage SSL certificate not found: /does/not/exist
     */
    public function testValidatesCert()
    {
        $f = new Handler\CurlFactory(3);
        $f->create(new Psr7\Request('GET', Server::$url), array('cert' => '/does/not/exist'));
    }

    public function testAddsCert()
    {
        $f = new Handler\CurlFactory(3);
        $f->create(new Psr7\Request('GET', Server::$url), array('cert' => __FILE__));
        $this->assertEquals(__FILE__, $_SERVER['_curl'][CURLOPT_SSLCERT]);
    }

    public function testAddsCertWithPassword()
    {
        $f = new Handler\CurlFactory(3);
        $f->create(new Psr7\Request('GET', Server::$url), array('cert' => array(__FILE__, 'test')));
        $this->assertEquals(__FILE__, $_SERVER['_curl'][CURLOPT_SSLCERT]);
        $this->assertEquals('test', $_SERVER['_curl'][CURLOPT_SSLCERTPASSWD]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage progress client option must be callable
     */
    public function testValidatesProgress()
    {
        $f = new Handler\CurlFactory(3);
        $f->create(new Psr7\Request('GET', Server::$url), array('progress' => 'foo'));
    }

    public function testEmitsDebugInfoToStream()
    {
        $res = fopen('php://memory', 'r+');
        Server::flush();
        Server::enqueue(array(new Psr7\Response()));
        $a = new Handler\CurlMultiHandler();
        $response = call_user_func($a, new Psr7\Request('HEAD', Server::$url), array('debug' => $res));
        $response->wait();
        rewind($res);
        $output = str_replace("\r", '', stream_get_contents($res));
        $this->assertContains("> HEAD / HTTP/1.1", $output);
        $this->assertContains("< HTTP/1.1 200", $output);
        fclose($res);
    }

    public function testEmitsProgressToFunction()
    {
        Server::flush();
        Server::enqueue(array(new Psr7\Response()));
        $a = new Handler\CurlMultiHandler();
        $called = array();
        $request = new Psr7\Request('HEAD', Server::$url);
        $response = call_user_func($a, $request, array(
            'progress' => function () use (&$called) {
                $called[] = func_get_args();
            },
        ));
        $response->wait();
        $this->assertNotEmpty($called);
        foreach ($called as $call) {
            $this->assertCount(4, $call);
        }
    }

    private function addDecodeResponse($withEncoding = true)
    {
        $content = gzencode('test');
        $headers = array('Content-Length' => strlen($content));
        if ($withEncoding) {
            $headers['Content-Encoding'] = 'gzip';
        }
        $response  = new Psr7\Response(200, $headers, $content);
        Server::flush();
        Server::enqueue(array($response));
        return $content;
    }

    public function testDecodesGzippedResponses()
    {
        $this->addDecodeResponse();
        $handler = new Handler\CurlMultiHandler();
        $request = new Psr7\Request('GET', Server::$url);
        $response = call_user_func($handler, $request, array('decode_content' => true));
        $response = $response->wait();
        $this->assertEquals('test', (string) $response->getBody());
        $this->assertEquals('', $_SERVER['_curl'][CURLOPT_ENCODING]);
        $received = Server::received();
        $sent = $received[0];
        $this->assertFalse($sent->hasHeader('Accept-Encoding'));
    }

    public function testReportsOriginalSizeAndContentEncodingAfterDecoding()
    {
        $this->addDecodeResponse();
        $handler = new Handler\CurlMultiHandler();
        $request = new Psr7\Request('GET', Server::$url);
        $response = call_user_func($handler, $request, array('decode_content' => true));
        $response = $response->wait();
        $this->assertSame(
            'gzip',
            $response->getHeaderLine('x-encoded-content-encoding')
        );
        $this->assertSame(
            strlen(gzencode('test')),
            (int) $response->getHeaderLine('x-encoded-content-length')
        );
    }

    public function testDecodesGzippedResponsesWithHeader()
    {
        $this->addDecodeResponse();
        $handler = new Handler\CurlMultiHandler();
        $request = new Psr7\Request('GET', Server::$url, array('Accept-Encoding' => 'gzip'));
        $response = call_user_func($handler, $request, array('decode_content' => true));
        $response = $response->wait();
        $this->assertEquals('gzip', $_SERVER['_curl'][CURLOPT_ENCODING]);
        $received = Server::received();
        $sent = $received[0];
        $this->assertEquals('gzip', $sent->getHeaderLine('Accept-Encoding'));
        $this->assertEquals('test', (string) $response->getBody());
        $this->assertFalse($response->hasHeader('content-encoding'));
        $this->assertTrue(!$response->hasHeader('content-length') || $response->getHeaderLine('content-length') == $response->getBody()->getSize());
    }

    public function testDoesNotForceDecode()
    {
        $content = $this->addDecodeResponse();
        $handler = new Handler\CurlMultiHandler();
        $request = new Psr7\Request('GET', Server::$url);
        $response = call_user_func($handler, $request, array('decode_content' => false));
        $response = $response->wait();
        $received = Server::received();
        $sent = $received[0];
        $this->assertFalse($sent->hasHeader('Accept-Encoding'));
        $this->assertEquals($content, (string) $response->getBody());
    }

    public function testProtocolVersion()
    {
        Server::flush();
        Server::enqueue(array(new Psr7\Response()));
        $a = new Handler\CurlMultiHandler();
        $request = new Psr7\Request('GET', Server::$url, array(), null, '1.0');
        call_user_func($a, $request, array());
        $this->assertEquals(CURL_HTTP_VERSION_1_0, $_SERVER['_curl'][CURLOPT_HTTP_VERSION]);
    }

    public function testSavesToStream()
    {
        $stream = fopen('php://memory', 'r+');
        $this->addDecodeResponse();
        $handler = new Handler\CurlMultiHandler();
        $request = new Psr7\Request('GET', Server::$url);
        $response = call_user_func($handler, $request, array(
            'decode_content' => true,
            'sink'           => $stream,
        ));
        $response->wait();
        rewind($stream);
        $this->assertEquals('test', stream_get_contents($stream));
    }

    public function testSavesToGuzzleStream()
    {
        $stream = Psr7\stream_for();
        $this->addDecodeResponse();
        $handler = new Handler\CurlMultiHandler();
        $request = new Psr7\Request('GET', Server::$url);
        $response = call_user_func($handler, $request, array(
            'decode_content' => true,
            'sink'           => $stream,
        ));
        $response->wait();
        $this->assertEquals('test', (string) $stream);
    }

    public function testSavesToFileOnDisk()
    {
        $tmpfile = tempnam(sys_get_temp_dir(), 'testfile');
        $this->addDecodeResponse();
        $handler = new Handler\CurlMultiHandler();
        $request = new Psr7\Request('GET', Server::$url);
        $response = call_user_func($handler, $request, array(
            'decode_content' => true,
            'sink'           => $tmpfile,
        ));
        $response->wait();
        $this->assertEquals('test', file_get_contents($tmpfile));
        unlink($tmpfile);
    }

    public function testDoesNotAddMultipleContentLengthHeaders()
    {
        $this->addDecodeResponse();
        $handler = new Handler\CurlMultiHandler();
        $request = new Psr7\Request('PUT', Server::$url, array('Content-Length' => 3), 'foo');
        $response = call_user_func($handler, $request, array());
        $response->wait();
        $received = Server::received();
        $sent = $received[0];
        $this->assertEquals(3, $sent->getHeaderLine('Content-Length'));
        $this->assertFalse($sent->hasHeader('Transfer-Encoding'));
        $this->assertEquals('foo', (string) $sent->getBody());
    }

    public function testSendsPostWithNoBodyOrDefaultContentType()
    {
        Server::flush();
        Server::enqueue(array(new Psr7\Response()));
        $handler = new Handler\CurlMultiHandler();
        $request = new Psr7\Request('POST', Server::$url);
        $response = call_user_func($handler, $request, array());
        $response->wait();
        $received = Server::received();
        $received = $received[0];
        $this->assertEquals('POST', $received->getMethod());
        $this->assertFalse($received->hasHeader('content-type'));
        $this->assertSame('0', $received->getHeaderLine('content-length'));
    }

    /**
     * @expectedException \GuzzleHttp\Guzzle\Exception\RequestException
     * @expectedExceptionMessage but attempting to rewind the request body failed
     */
    public function testFailsWhenCannotRewindRetryAfterNoResponse()
    {
        $factory = new Handler\CurlFactory(1);
        $stream = Psr7\stream_for('abc');
        $stream->read(1);
        $stream = new Psr7\NoSeekStream($stream);
        $request = new Psr7\Request('PUT', Server::$url, array(), $stream);
        $fn = function ($request, $options) use (&$fn, $factory) {
            $easy = $factory->create($request, $options);
            return Handler\CurlFactory::finish($fn, $easy, $factory);
        };
        call_user_func($fn, $request, array())->wait();
    }

    public function testRetriesWhenBodyCanBeRewound()
    {
        $callHandler = $called = false;

        $fn = function ($r, $options) use (&$callHandler) {
            $callHandler = true;
            return \GuzzleHttp\Promise\promise_for(new Psr7\Response());
        };

        $bd = Psr7\FnStream::decorate(Psr7\stream_for('test'), array(
            'tell'   => function () { return 1; },
            'rewind' => function () use (&$called) { $called = true; }
        ));

        $factory = new Handler\CurlFactory(1);
        $req = new Psr7\Request('PUT', Server::$url, array(), $bd);
        $easy = $factory->create($req, array());
        $res = Handler\CurlFactory::finish($fn, $easy, $factory);
        $res = $res->wait();
        $this->assertTrue($callHandler);
        $this->assertTrue($called);
        $this->assertEquals('200', $res->getStatusCode());
    }

    /**
     * @expectedException \GuzzleHttp\Guzzle\Exception\RequestException
     * @expectedExceptionMessage The cURL request was retried 3 times
     */
    public function testFailsWhenRetryMoreThanThreeTimes()
    {
        $factory = new Handler\CurlFactory(1);
        $call = 0;
        $fn = function ($request, $options) use (&$mock, &$call, $factory) {
            $call++;
            $easy = $factory->create($request, $options);
            return Handler\CurlFactory::finish($mock, $easy, $factory);
        };
        $mock = new Handler\MockHandler(array($fn, $fn, $fn));
        $p = call_user_func($mock, new Psr7\Request('PUT', Server::$url, array(), 'test'), array());
        $p->wait(false);
        $this->assertEquals(3, $call);
        $p->wait(true);
    }

    public function testHandles100Continue()
    {
        Server::flush();
        Server::enqueue(array(
            new Psr7\Response(200, array('Test' => 'Hello', 'Content-Length' => 4), 'test'),
        ));
        $request = new Psr7\Request('PUT', Server::$url, array(
            'Expect' => '100-Continue'
        ), 'test');
        $handler = new Handler\CurlMultiHandler();
        $response = call_user_func($handler, $request, array())->wait();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
        $this->assertEquals('Hello', $response->getHeaderLine('Test'));
        $this->assertEquals('4', $response->getHeaderLine('Content-Length'));
        $this->assertEquals('test', (string) $response->getBody());
    }

    /**
     * @expectedException \GuzzleHttp\Guzzle\Exception\ConnectException
     */
    public function testCreatesConnectException()
    {
        $m = new \ReflectionMethod('\GuzzleHttp\Guzzle\Handler\CurlFactory', 'finishError');
        $m->setAccessible(true);
        $factory = new Handler\CurlFactory(1);
        $easy = $factory->create(new Psr7\Request('GET', Server::$url), array());
        $easy->errno = CURLE_COULDNT_CONNECT;
        $response = $m->invoke(
            null,
            function () {},
            $easy,
            $factory
        );
        $response->wait();
    }

    public function testAddsTimeouts()
    {
        $f = new Handler\CurlFactory(3);
        $f->create(new Psr7\Request('GET', Server::$url), array(
            'timeout'         => 0.1,
            'connect_timeout' => 0.2
        ));
        $this->assertEquals(100, $_SERVER['_curl'][CURLOPT_TIMEOUT_MS]);
        $this->assertEquals(200, $_SERVER['_curl'][CURLOPT_CONNECTTIMEOUT_MS]);
    }

    public function testAddsStreamingBody()
    {
        $f = new Handler\CurlFactory(3);
        $bd = Psr7\FnStream::decorate(Psr7\stream_for('foo'), array(
            'getSize' => function () {
                return null;
            }
        ));
        $request = new Psr7\Request('PUT', Server::$url, array(), $bd);
        $f->create($request, array());
        $this->assertEquals(1, $_SERVER['_curl'][CURLOPT_UPLOAD]);
        $this->assertTrue(is_callable($_SERVER['_curl'][CURLOPT_READFUNCTION]));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Directory /does/not/exist/so does not exist for sink value of /does/not/exist/so/error.txt
     */
    public function testEnsuresDirExistsBeforeThrowingWarning()
    {
        $f = new Handler\CurlFactory(3);
        $f->create(new Psr7\Request('GET', Server::$url), array(
            'sink' => '/does/not/exist/so/error.txt'
        ));
    }

    public function testClosesIdleHandles()
    {
        $f = new Handler\CurlFactory(3);
        $req = new Psr7\Request('GET', Server::$url);
        $easy = $f->create($req, array());
        $h1 = $easy->handle;
        $f->release($easy);
        $this->assertCount(1, $this->readAttribute($f, 'handles'));
        $easy = $f->create($req, array());
        if (function_exists('curl_reset')) {
            $this->assertSame($easy->handle, $h1);
        }
        $easy2 = $f->create($req, array());
        $easy3 = $f->create($req, array());
        $easy4 = $f->create($req, array());
        $f->release($easy);
        $this->assertCount(1, $this->readAttribute($f, 'handles'));
        $f->release($easy2);
        $this->assertCount(2, $this->readAttribute($f, 'handles'));
        $f->release($easy3);
        $this->assertCount(3, $this->readAttribute($f, 'handles'));
        $f->release($easy4);
        $this->assertCount(3, $this->readAttribute($f, 'handles'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testEnsuresOnHeadersIsCallable()
    {
        $req = new Psr7\Request('GET', Server::$url);
        $handler = new Handler\CurlHandler();
        call_user_func($handler, $req, array('on_headers' => 'error!'));
    }

    /**
     * @expectedException \GuzzleHttp\Guzzle\Exception\RequestException
     * @expectedExceptionMessage An error was encountered during the on_headers event
     * @expectedExceptionMessage test
     */
    public function testRejectsPromiseWhenOnHeadersFails()
    {
        Server::flush();
        Server::enqueue(array(
            new Psr7\Response(200, array('X-Foo' => 'bar'), 'abc 123')
        ));
        $req = new Psr7\Request('GET', Server::$url);
        $handler = new Handler\CurlHandler();
        $promise = call_user_func($handler, $req, array(
            'on_headers' => function () {
                throw new \Exception('test');
            }
        ));
        $promise->wait();
    }

    public function testSuccessfullyCallsOnHeadersBeforeWritingToSink()
    {
        Server::flush();
        Server::enqueue(array(
            new Psr7\Response(200, array('X-Foo' => 'bar'), 'abc 123')
        ));
        $req = new Psr7\Request('GET', Server::$url);
        $got = null;

        $stream = Psr7\stream_for();
        $assertNotNull = array($this, 'assertNotNull');
        $assertEquals = array($this, 'assertEquals');
        $stream = Psr7\FnStream::decorate($stream, array(
            'write' => function ($data) use ($stream, &$got, $assertNotNull) {
                call_user_func($assertNotNull, $got);
                return $stream->write($data);
            }
        ));

        $handler = new Handler\CurlHandler();
        $promise = call_user_func($handler, $req, array(
            'sink'       => $stream,
            'on_headers' => function (ResponseInterface $res) use (&$got, $assertEquals) {
                $got = $res;
                call_user_func($assertEquals, 'bar', $res->getHeaderLine('X-Foo'));
            }
        ));

        $response = $promise->wait();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('bar', $response->getHeaderLine('X-Foo'));
        $this->assertEquals('abc 123', (string) $response->getBody());
    }

    public function testInvokesOnStatsOnSuccess()
    {
        Server::flush();
        Server::enqueue(array(new Psr7\Response(200)));
        $req = new Psr7\Request('GET', Server::$url);
        $gotStats = null;
        $handler = new Handler\CurlHandler();
        $promise = call_user_func($handler, $req, array(
            'on_stats' => function (TransferStats $stats) use (&$gotStats) {
                $gotStats = $stats;
            }
        ));
        $response = $promise->wait();
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(200, $gotStats->getResponse()->getStatusCode());
        $this->assertEquals(
            Server::$url,
            (string) $gotStats->getEffectiveUri()
        );
        $this->assertEquals(
            Server::$url,
            (string) $gotStats->getRequest()->getUri()
        );
        $this->assertGreaterThan(0, $gotStats->getTransferTime());
    }

    public function testInvokesOnStatsOnError()
    {
        $req = new Psr7\Request('GET', 'http://127.0.0.1:123');
        $gotStats = null;
        $handler = new Handler\CurlHandler();
        $promise = call_user_func($handler, $req, array(
            'connect_timeout' => 0.001,
            'timeout' => 0.001,
            'on_stats' => function (TransferStats $stats) use (&$gotStats) {
                $gotStats = $stats;
            }
        ));
        $promise->wait(false);
        $this->assertFalse($gotStats->hasResponse());
        $this->assertEquals(
            'http://127.0.0.1:123',
            $gotStats->getEffectiveUri()
        );
        $this->assertEquals(
            'http://127.0.0.1:123',
            $gotStats->getRequest()->getUri()
        );
        $this->assertInternalType('float', $gotStats->getTransferTime());
        $this->assertInternalType('int', $gotStats->getHandlerErrorData());
    }

    public function testRewindsBodyIfPossible()
    {
        $body = Psr7\stream_for(str_repeat('x', 1024 * 1024 * 2));
        $body->seek(1024 * 1024);
        $this->assertSame(1024 * 1024, $body->tell());

        $req = new Psr7\Request('POST', 'https://www.example.com', array(
            'Content-Length' => 1024 * 1024 * 2,
        ), $body);
        $factory = new CurlFactory(1);
        $factory->create($req, array());

        $this->assertSame(0, $body->tell());
    }

    public function testDoesNotRewindUnseekableBody()
    {
        $body = Psr7\stream_for(str_repeat('x', 1024 * 1024 * 2));
        $body->seek(1024 * 1024);
        $body = new Psr7\NoSeekStream($body);
        $this->assertSame(1024 * 1024, $body->tell());

        $req = new Psr7\Request('POST', 'https://www.example.com', array(
            'Content-Length' => 1024 * 1024,
        ), $body);
        $factory = new CurlFactory(1);
        $factory->create($req, array());

        $this->assertSame(1024 * 1024, $body->tell());
    }
}
