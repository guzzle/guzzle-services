<?php

namespace GuzzleHttp\Tests\Command\Guzzle\RequestLocation;

use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\RequestLocation\HeaderLocation;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Command\Guzzle\RequestLocation\HeaderLocation
 * @covers \GuzzleHttp\Command\Guzzle\RequestLocation\AbstractLocation
 */
class HeaderLocationTest extends TestCase
{
    /**
     * @group RequestLocation
     */
    public function testVisitsLocation()
    {
        $location = new HeaderLocation('header');
        $command = new Command('foo', ['foo' => 'bar']);
        $request = new Request('POST', 'http://httbin.org');
        $param = new Parameter(['name' => 'foo']);
        $request = $location->visit($command, $request, $param);

        $header = $request->getHeader('foo');
        $this->assertIsArray($header);
        $this->assertEquals([0 => 'bar'], $request->getHeader('foo'));
    }

    /**
     * @group RequestLocation
     */
    public function testVisitsLocationSerializesScalarHeaderValues()
    {
        $location = new HeaderLocation('header');
        $param = new Parameter(['name' => 'foo']);

        $request = $location->visit(
            new Command('foo', ['foo' => 123]),
            new Request('POST', 'http://httbin.org'),
            $param
        );
        $this->assertEquals([0 => '123'], $request->getHeader('foo'));

        $request = $location->visit(
            new Command('foo', ['foo' => true]),
            new Request('POST', 'http://httbin.org'),
            $param
        );
        $this->assertEquals([0 => '1'], $request->getHeader('foo'));

        $request = $location->visit(
            new Command('foo', ['foo' => false]),
            new Request('POST', 'http://httbin.org'),
            $param
        );
        $this->assertEquals([0 => ''], $request->getHeader('foo'));
    }

    /**
     * @group RequestLocation
     */
    public function testVisitsLocationSerializesArrayHeaderValues()
    {
        $location = new HeaderLocation('header');
        $command = new Command('foo', ['foo' => ['bar', 123, true, false]]);
        $request = new Request('POST', 'http://httbin.org');
        $param = new Parameter(['name' => 'foo']);

        $request = $location->visit($command, $request, $param);

        $this->assertEquals([0 => 'bar', 1 => '123', 2 => '1', 3 => ''], $request->getHeader('foo'));
    }

    /**
     * @group RequestLocation
     */
    public function testAddsAdditionalProperties()
    {
        $location = new HeaderLocation('header');
        $command = new Command('foo', ['foo' => 'bar']);
        $command['add'] = 'props';
        $operation = new Operation([
            'additionalParameters' => [
                'location' => 'header',
            ],
        ]);
        $request = new Request('POST', 'http://httbin.org');
        $request = $location->after($command, $request, $operation);

        $header = $request->getHeader('add');
        $this->assertIsArray($header);
        $this->assertEquals([0 => 'props'], $header);
    }

    /**
     * @group RequestLocation
     */
    public function testAdditionalPropertiesSerializeScalarHeaderValues()
    {
        $location = new HeaderLocation('header');
        $command = new Command('foo', ['foo' => 'bar']);
        $command['add'] = 123;
        $operation = new Operation([
            'additionalParameters' => [
                'location' => 'header',
            ],
        ]);
        $request = new Request('POST', 'http://httbin.org');
        $request = $location->after($command, $request, $operation);

        $header = $request->getHeader('add');
        $this->assertIsArray($header);
        $this->assertEquals([0 => '123'], $header);
    }
}
