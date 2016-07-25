<?php
namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\RequestLocation\PostFieldLocation;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Psr7\Request;

/**
 * @covers \GuzzleHttp\Command\Guzzle\RequestLocation\PostFieldLocation
 * @covers \GuzzleHttp\Command\Guzzle\RequestLocation\AbstractLocation
 */
class PostFieldLocationTest extends \PHPUnit_Framework_TestCase
{
    public function testVisitsLocation()
    {
        $this->markTestIncomplete('Figure out how this works in Guzzle 6');
        $location = new PostFieldLocation('body');
        $command = new Command('foo', ['foo' => 'bar']);
        $request = new Request('POST', 'http://httbin.org', []);
        $param = new Parameter(['name' => 'foo']);
        $location->visit($command, $request, $param);
        $this->assertEquals('bar', $request->getBody()->getField('foo'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testValidatesBodyIsPost()
    {
        $this->markTestIncomplete('Figure out how this works in Guzzle 6');
        $location = new PostFieldLocation('postField');
        $command = new Command('foo', ['foo' => 'bar']);
        $request = new Request('POST', 'http://httbin.org');
        $param = new Parameter(['name' => 'foo']);
        $location->visit($command, $request, $param);
    }

    public function testAddsAdditionalProperties()
    {
        $this->markTestIncomplete('Figure out how this works in Guzzle 6');
        $location = new PostFieldLocation('postField');
        $command = new Command('foo', ['foo' => 'bar']);
        $command['add'] = 'props';
        $operation = new Operation([
            'additionalParameters' => [
                'location' => 'postField'
            ]
        ], new Description([]));
        $request = new Request('POST', 'http://httbin.org', []);
        $location->after($command, $request, $operation, []);
        $this->assertEquals('props', $request->getBody()->getField('add'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testValidatesBodyInAfter()
    {
        $this->markTestIncomplete('Figure out how this works in Guzzle 6');
        $location = new PostFieldLocation('postField');
        $command = new Command('foo', ['foo' => 'bar']);
        $operation = new Operation([
            'additionalParameters' => [
                'location' => 'postField'
            ]
        ], new Description([]));
        $request = new Request('POST', 'http://httbin.org');
        $location->after($command, $request, $operation, []);
    }
}
