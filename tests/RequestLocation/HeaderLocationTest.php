<?php

namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Command\Guzzle\RequestLocation\HeaderLocation;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\Description;

/**
 * @covers \GuzzleHttp\Command\Guzzle\RequestLocation\HeaderLocation
 * @covers \GuzzleHttp\Command\Guzzle\RequestLocation\AbstractLocation
 */
class HeaderLocationTest extends AbstractLocationTest
{
    public function testVisitsLocation()
    {
        $location = new HeaderLocation('header');
        $command = $this->getCommand();
        $request = new Request('POST', 'http://httbin.org');
        $param = new Parameter(['name' => 'foo']);
        $location->visit($command, $request, $param, []);
        $this->assertEquals('bar', $request->getHeader('foo'));
    }

    public function testAddsAdditionalProperties()
    {
        $location = new HeaderLocation('header');
        $command = $this->getCommand();
        $command['add'] = 'props';
        $operation = new Operation(
            [
                'additionalParameters' => [
                    'location' => 'header'
                ]
            ],
            new Description([])
        );
        $request = new Request('POST', 'http://httbin.org');
        $location->after($command, $request, $operation, []);
        $this->assertEquals('props', $request->getHeader('add'));
    }
}
