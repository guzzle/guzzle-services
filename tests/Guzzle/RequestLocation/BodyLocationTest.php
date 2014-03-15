<?php

namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\RequestLocation\BodyLocation;
use GuzzleHttp\Message\Request;

/**
 * @covers \GuzzleHttp\Command\Guzzle\RequestLocation\BodyLocation
 */
class BodyLocationTest extends AbstractLocationTest
{
    public function testVisitsLocation()
    {
        $location = new BodyLocation('body');
        $command = $this->getCommand();
        $request = new Request('POST', 'http://httbin.org');
        $param = new Parameter(['name' => 'foo']);
        $location->visit($command, $request, $param, []);
        $this->assertEquals('bar', $request->getBody());
    }
}
