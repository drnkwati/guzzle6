<?php
namespace GuzzleHttp\Tests\CookieJar;

use GuzzleHttp\Guzzle\Cookie\FileCookieJar;
use GuzzleHttp\Guzzle\Cookie\SetCookie;

/**
 * @covers \GuzzleHttp\Guzzle\Cookie\FileCookieJar
 */
class FileCookieJarTest extends \PHPUnit_Framework_TestCase
{
    private $file;

    public function setUp()
    {
        $this->file = tempnam('/tmp', 'file-cookies');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testValidatesCookieFile()
    {
        file_put_contents($this->file, 'true');
        new FileCookieJar($this->file);
    }

    public function testLoadsFromFile()
    {
        $jar = new FileCookieJar($this->file);
        $this->assertEquals(array(), $jar->getIterator()->getArrayCopy());
        unlink($this->file);
    }

    /**
     * @dataProvider testPersistsToFileFileParameters
     */
    public function testPersistsToFile($testSaveSessionCookie = false)
    {
        $jar = new FileCookieJar($this->file, $testSaveSessionCookie);
        $jar->setCookie(new SetCookie(array(
            'Name'    => 'foo',
            'Value'   => 'bar',
            'Domain'  => 'foo.com',
            'Expires' => time() + 1000
        )));
        $jar->setCookie(new SetCookie(array(
            'Name'    => 'baz',
            'Value'   => 'bar',
            'Domain'  => 'foo.com',
            'Expires' => time() + 1000
        )));
        $jar->setCookie(new SetCookie(array(
            'Name'    => 'boo',
            'Value'   => 'bar',
            'Domain'  => 'foo.com',
        )));

        $this->assertEquals(3, count($jar));
        unset($jar);

        // Make sure it wrote to the file
        $contents = file_get_contents($this->file);
        $this->assertNotEmpty($contents);

        // Load the cookieJar from the file
        $jar = new FileCookieJar($this->file);

        if ($testSaveSessionCookie) {
            $this->assertEquals(3, count($jar));
        } else {
            // Weeds out temporary and session cookies
            $this->assertEquals(2, count($jar));
        }

        unset($jar);
        unlink($this->file);
    }

    public function testPersistsToFileFileParameters()
    {
        return array(
            array(false),
            array(true)
        );
    }
}
