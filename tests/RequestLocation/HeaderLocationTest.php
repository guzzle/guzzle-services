<?php

declare(strict_types=1);

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
    public function testVisitsLocation(): void
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
    public function testVisitsLocationAcceptsArrayHeaderValues(): void
    {
        $location = new HeaderLocation('header');
        $command = new Command('foo', ['foo' => ['bar', 'baz']]);
        $request = new Request('POST', 'http://httbin.org');
        $param = new Parameter(['name' => 'foo']);

        $request = $location->visit($command, $request, $param);

        $this->assertEquals([0 => 'bar', 1 => 'baz'], $request->getHeader('foo'));
    }

    /**
     * @group RequestLocation
     */
    public function testVisitsLocationRejectsInvalidHeaderValue(): void
    {
        $location = new HeaderLocation('header');
        $command = new Command('foo', ['foo' => new \stdClass()]);
        $request = new Request('POST', 'http://httbin.org');
        $param = new Parameter(['name' => 'foo']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Header location values must be strings or non-empty arrays of strings.');

        $location->visit($command, $request, $param);
    }

    /**
     * @group RequestLocation
     */
    public function testVisitsLocationRejectsScalarHeaderValue(): void
    {
        $location = new HeaderLocation('header');
        $command = new Command('foo', ['foo' => 123]);
        $request = new Request('POST', 'http://httbin.org');
        $param = new Parameter(['name' => 'foo']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Header location values must be strings or non-empty arrays of strings.');

        $location->visit($command, $request, $param);
    }

    /**
     * @group RequestLocation
     */
    public function testVisitsLocationRejectsInvalidArrayHeaderValue(): void
    {
        $location = new HeaderLocation('header');
        $command = new Command('foo', ['foo' => ['bar', 123]]);
        $request = new Request('POST', 'http://httbin.org');
        $param = new Parameter(['name' => 'foo']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Header location values must be strings or non-empty arrays of strings.');

        $location->visit($command, $request, $param);
    }

    /**
     * @group RequestLocation
     */
    public function testVisitsLocationRejectsEmptyArrayHeaderValue(): void
    {
        $location = new HeaderLocation('header');
        $command = new Command('foo', ['foo' => []]);
        $request = new Request('POST', 'http://httbin.org');
        $param = new Parameter(['name' => 'foo']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Header location values must be strings or non-empty arrays of strings.');

        $location->visit($command, $request, $param);
    }

    /**
     * @group RequestLocation
     */
    public function testAddsAdditionalProperties(): void
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
    public function testAdditionalPropertiesRejectInvalidHeaderValue(): void
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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Header location values must be strings or non-empty arrays of strings.');

        $location->after($command, $request, $operation);
    }

    /**
     * @group RequestLocation
     */
    public function testAdditionalPropertiesRejectEmptyArrayHeaderValue(): void
    {
        $location = new HeaderLocation('header');
        $command = new Command('foo', ['foo' => 'bar']);
        $command['add'] = [];
        $operation = new Operation([
            'additionalParameters' => [
                'location' => 'header',
            ],
        ]);
        $request = new Request('POST', 'http://httbin.org');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Header location values must be strings or non-empty arrays of strings.');

        $location->after($command, $request, $operation);
    }
}
