<?php
namespace GuzzleHttp\Tests\Command\Guzzle\RequestLocation;

use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\RequestLocation\QueryLocation;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7;

/**
 * @covers \GuzzleHttp\Command\Guzzle\RequestLocation\QueryLocation
 * @covers \GuzzleHttp\Command\Guzzle\RequestLocation\AbstractLocation
 */
class QueryLocationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group RequestLocation
     */
    public function testVisitsLocation()
    {
        $location = new QueryLocation();
        $command = new Command('foo', ['foo' => 'bar']);
        $request = new Request('POST', 'http://httbin.org');
        $param = new Parameter(['name' => 'foo']);
        $request = $location->visit($command, $request, $param);

        $this->assertEquals('bar', Psr7\parse_query($request->getUri()->getQuery())['foo']);
    }

    /**
     * @group RequestLocation
     */
    public function testAddsAdditionalProperties()
    {
        $location = new QueryLocation();
        $command = new Command('foo', ['foo' => 'bar']);
        $command['add'] = 'props';
        $operation = new Operation([
            'additionalParameters' => [
                'location' => 'query'
            ]
        ], new Description([]));
        $request = new Request('POST', 'http://httbin.org');
        $request = $location->after($command, $request, $operation);

        $this->assertEquals('props', Psr7\parse_query($request->getUri()->getQuery())['add']);
    }
}
