<?php
namespace GuzzleHttp\Tests\Command\Guzzle\ResponseLocation;

use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\ResponseLocation\ReasonPhraseLocation;
use GuzzleHttp\Command\Result;
use GuzzleHttp\Psr7\Response;

/**
 * @covers \GuzzleHttp\Command\Guzzle\ResponseLocation\ReasonPhraseLocation
 * @covers \GuzzleHttp\Command\Guzzle\ResponseLocation\AbstractLocation
 */
class ReasonPhraseLocationTest extends \PHPUnit_Framework_TestCase
{
    public function testVisitsLocation()
    {
        $l = new ReasonPhraseLocation('reasonPhrase');
        $parameter = new Parameter([
            'name' => 'val',
            'filters' => ['strtolower']
        ]);
        $response = new Response(200);
        $result = new Result();
        $result = $l->visit($result, $response, $parameter);
        $this->assertEquals('ok', $result['val']);
    }
}
