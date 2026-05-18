<?php

namespace GuzzleHttp\Tests\Command\Guzzle\RequestLocation;

use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\RequestLocation\MultiPartLocation;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Command\Guzzle\RequestLocation\MultiPartLocation
 */
class MultiPartLocationTest extends TestCase
{
    /**
     * @group RequestLocation
     */
    public function testVisitsLocation()
    {
        $location = new MultiPartLocation();
        $command = new Command('foo', ['foo' => 'bar']);
        $request = new Request('POST', 'http://httbin.org', []);
        $param = new Parameter(['name' => 'foo']);
        $request = $location->visit($command, $request, $param);
        $operation = new Operation();
        $request = $location->after($command, $request, $operation);
        $body = $request->getBody();
        $actual = $body->getContents();

        $this->assertNotFalse(strpos($actual, 'name="foo"'));
        $this->assertNotFalse(strpos($actual, 'bar'));
        $this->assertInstanceOf(MultipartStream::class, $body);
        $this->assertSame('multipart/form-data; boundary='.$body->getBoundary(), $request->getHeaderLine('Content-Type'));
    }

    /**
     * @group RequestLocation
     */
    public function testVisitsLocationDoesNotOverwriteContentTypeHeader()
    {
        $location = new MultiPartLocation();
        $command = new Command('foo', ['foo' => 'bar']);
        $request = new Request('POST', 'http://httbin.org', ['Content-Type' => 'application/vnd.example']);
        $param = new Parameter(['name' => 'foo']);
        $request = $location->visit($command, $request, $param);
        $operation = new Operation();
        $request = $location->after($command, $request, $operation);
        $actual = $request->getBody()->getContents();

        $this->assertNotFalse(strpos($actual, 'name="foo"'));
        $this->assertNotFalse(strpos($actual, 'bar'));
        $this->assertSame('application/vnd.example', $request->getHeaderLine('Content-Type'));
    }
}
