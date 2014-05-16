<?php

namespace GuzzleHttp\Tests\Command\Guzzle\ResponseLocation;

use GuzzleHttp\Client;
use GuzzleHttp\Command\Guzzle\Command;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\ResponseLocation\JsonLocation;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;

/**
 * @covers \GuzzleHttp\Command\Guzzle\ResponseLocation\JsonLocation
 */
class JsonLocationTest extends \PHPUnit_Framework_TestCase
{
    public function testVisitsLocation()
    {
        $l = new JsonLocation('json');
        $operation = new Operation([], new Description([]));
        $command = new Command($operation, []);
        $parameter = new Parameter([
            'name'    => 'val',
            'sentAs'  => 'vim',
            'filters' => ['strtoupper']
        ]);
        $response = new Response(200, [], Stream::factory('{"vim":"bar"}'));
        $result = [];
        $l->before($command, $response, $parameter, $result);
        $l->visit($command, $response, $parameter, $result);
        $this->assertEquals('BAR', $result['val']);
    }

    public function testVisitsAdditionalProperties()
    {
        $l = new JsonLocation('json');
        $operation = new Operation([], new Description([]));
        $command = new Command($operation, []);
        $parameter = new Parameter();
        $model = new Parameter(['additionalProperties' => ['location' => 'json']]);
        $response = new Response(200, [], Stream::factory('{"vim":"bar","qux":[1,2]}'));
        $result = [];
        $l->before($command, $response, $parameter, $result);
        $l->visit($command, $response, $parameter, $result);
        $l->after($command, $response, $model, $result);
        $this->assertEquals('bar', $result['vim']);
        $this->assertEquals([1, 2], $result['qux']);
    }

    public function testVisitsAdditionalPropertiesWithEmptyResponse()
    {
        $l = new JsonLocation('json');
        $operation = new Operation([], new Description([]));
        $command = new Command($operation, []);
        $parameter = new Parameter();
        $model = new Parameter(['additionalProperties' => ['location' => 'json']]);
        $response = new Response(204);
        $result = [];
        $l->before($command, $response, $parameter, $result);
        $l->visit($command, $response, $parameter, $result);
        $l->after($command, $response, $model, $result);
        $this->assertEquals([], $result);
    }

    public function jsonProvider()
    {
        return [
            [null, [['foo' => 'BAR'], ['baz' => 'BAM']]],
            ['under_me', ['under_me' => [['foo' => 'BAR'], ['baz' => 'BAM']]]],
        ];
    }

    /**
     * @dataProvider jsonProvider
     */
    public function testVisitsTopLevelArrays($name, $expected)
    {
        $hclient = new Client();

        $hclient->getEmitter()->on('before', function (BeforeEvent $event) {
            $json = [
                ['foo' => 'bar'],
                ['baz' => 'bam'],
            ];
            $response = new Response(200, [
                'Content-Type' => 'application/json'
            ], Stream::factory(json_encode($json)));
            $event->intercept($response);
        });

        $description = new Description([
            'operations' => [
                'foo' => [
                    'uri' => 'http://httpbin.org',
                    'httpMethod' => 'GET',
                    'responseModel' => 'j'
                ]
            ],
            'models' => [
                'j' => [
                    'type' => 'array',
                    'location' => 'json',
                    'name' => $name,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => [
                            'type' => 'string',
                            'filters' => ['strtoupper']
                        ]
                    ]
                ]
            ]
        ]);
        $client = new GuzzleClient($hclient, $description);
        $result = $client->foo();
        $this->assertEquals($expected, $result->toArray());
    }

    public function testVisitsNestedProperties()
    {
        $hclient = new Client();

        $hclient->getEmitter()->on('before', function (BeforeEvent $event) {
            $json = [
                'nested' => [
                    'foo' => 'abc',
                    'bar' => 123,
                    'bam' => [
                        'abc' => 456
                    ]
                ],
                'baz' => 'boo'
            ];
            $response = new Response(200, [
                'Content-Type' => 'application/json'
            ], Stream::factory(json_encode($json)));
            $event->intercept($response);
        });

        $description = new Description([
            'operations' => [
                'foo' => [
                    'uri' => 'http://httpbin.org',
                    'httpMethod' => 'GET',
                    'responseModel' => 'j'
                ]
            ],
            'models' => [
                'j' => [
                    'type' => 'object',
                    'properties' => [
                        'nested' => [
                            'location' => 'json',
                            'type' => 'object',
                            'properties' => [
                                'foo' => ['type' => 'string'],
                                'bar' => ['type' => 'number'],
                                'bam' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'abc' => [
                                            'type' => 'number'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'additionalProperties' => [
                        'location' => 'json',
                        'type' => 'string',
                        'filters' => ['strtoupper']
                    ]
                ]
            ]
        ]);

        $client = new GuzzleClient($hclient, $description);
        $result = $client->foo();
        $expected = [
            'nested' => [
                'foo' => 'abc',
                'bar' => 123,
                'bam' => [
                    'abc' => 456
                ]
            ],
            'baz' => 'BOO'
        ];

        $this->assertEquals($expected, $result->toArray());
    }
}
