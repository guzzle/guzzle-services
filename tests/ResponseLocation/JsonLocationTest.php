<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Command\Guzzle\ResponseLocation;

use GuzzleHttp\Client;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\ResponseLocation\JsonLocation;
use GuzzleHttp\Command\Result;
use GuzzleHttp\Command\ResultInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Utils;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Command\Guzzle\ResponseLocation\JsonLocation
 * @covers \GuzzleHttp\Command\Guzzle\Deserializer
 */
class JsonLocationTest extends TestCase
{
    /**
     * @group ResponseLocation
     */
    public function testVisitsLocation(): void
    {
        $location = new JsonLocation();
        $parameter = new Parameter([
            'name' => 'val',
            'sentAs' => 'vim',
            'filters' => ['strtoupper'],
        ]);
        $response = new Response(200, [], '{"vim":"bar"}');
        $result = new Result();
        $result = $location->before($result, $response, $parameter);
        $result = $location->visit($result, $response, $parameter);
        $this->assertEquals('BAR', $result['val']);
    }

    /**
     * @group ResponseLocation
     */
    public function testVisitsWiredArray(): void
    {
        $json = ['car_models' => ['ferrari', 'aston martin']];
        $body = Utils::jsonEncode($json);
        $response = new Response(200, ['Content-Type' => 'application/json'], $body);
        $mock = new MockHandler([$response]);

        $guzzle = new Client(['handler' => $mock]);

        $description = new Description([
            'operations' => [
                'getCars' => [
                    'uri' => 'http://httpbin.org',
                    'httpMethod' => 'GET',
                    'responseModel' => 'Cars',
                ],
            ],
            'models' => [
                'Cars' => [
                    'type' => 'object',
                    'location' => 'json',
                    'properties' => [
                        'cars' => [
                            'type' => 'array',
                            'sentAs' => 'car_models',
                            'items' => [
                                'type' => 'object',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $guzzle = new GuzzleClient($guzzle, $description);
        $result = $guzzle->getCars();

        $this->assertEquals(['cars' => ['ferrari', 'aston martin']], $result->toArray());
    }

    /**
     * @group ResponseLocation
     */
    public function testVisitsAdditionalProperties(): void
    {
        $location = new JsonLocation();
        $parameter = new Parameter();
        $model = new Parameter(['additionalProperties' => ['location' => 'json']]);
        $response = new Response(200, [], '{"vim":"bar","qux":[1,2]}');
        $result = new Result();
        $result = $location->before($result, $response, $parameter);
        $result = $location->visit($result, $response, $parameter);
        $result = $location->after($result, $response, $model);
        $this->assertEquals('bar', $result['vim']);
        $this->assertEquals([1, 2], $result['qux']);
    }

    /**
     * @group ResponseLocation
     */
    public function testVisitsAdditionalPropertiesWithEmptyResponse(): void
    {
        $location = new JsonLocation();
        $parameter = new Parameter();
        $model = new Parameter(['additionalProperties' => ['location' => 'json']]);
        $response = new Response(204);
        $result = new Result();
        $result = $location->before($result, $response, $parameter);
        $result = $location->visit($result, $response, $parameter);
        $result = $location->after($result, $response, $model);
        $this->assertEquals([], $result->toArray());
    }

    public static function jsonProvider(): array
    {
        return [
            [null, [['foo' => 'BAR'], ['baz' => 'BAM']]],
            ['under_me', ['under_me' => [['foo' => 'BAR'], ['baz' => 'BAM']]]],
        ];
    }

    /**
     * @dataProvider jsonProvider
     *
     * @group ResponseLocation
     */
    public function testVisitsTopLevelArrays(?string $name, array $expected): void
    {
        $json = [
            ['foo' => 'bar'],
            ['baz' => 'bam'],
        ];
        $body = Utils::jsonEncode($json);
        $response = new Response(200, ['Content-Type' => 'application/json'], $body);
        $mock = new MockHandler([$response]);

        $guzzle = new Client(['handler' => $mock]);

        $description = new Description([
            'operations' => [
                'foo' => [
                    'uri' => 'http://httpbin.org',
                    'httpMethod' => 'GET',
                    'responseModel' => 'j',
                ],
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
                            'filters' => ['strtoupper'],
                        ],
                    ],
                ],
            ],
        ]);
        $guzzle = new GuzzleClient($guzzle, $description);
        /** @var ResultInterface */
        $result = $guzzle->foo();
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * @group ResponseLocation
     */
    public function testVisitsNestedArrays(): void
    {
        $json = [
            'scalar' => 'foo',
            'nested' => [
                'bar',
                'baz',
            ],
        ];
        $body = Utils::jsonEncode($json);
        $response = new Response(200, ['Content-Type' => 'application/json'], $body);
        $mock = new MockHandler([$response]);

        $httpClient = new Client(['handler' => $mock]);

        $description = new Description([
            'operations' => [
                'foo' => [
                    'uri' => 'http://httpbin.org',
                    'httpMethod' => 'GET',
                    'responseModel' => 'j',
                ],
            ],
            'models' => [
                'j' => [
                    'type' => 'object',
                    'location' => 'json',
                    'properties' => [
                        'scalar' => ['type' => 'string'],
                        'nested' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ]);
        $guzzle = new GuzzleClient($httpClient, $description);
        /** @var ResultInterface */
        $result = $guzzle->foo();
        $expected = [
            'scalar' => 'foo',
            'nested' => [
                'bar',
                'baz',
            ],
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    public static function nestedProvider(): array
    {
        return [
            [
                [
                    'operations' => [
                        'foo' => [
                            'uri' => 'http://httpbin.org',
                            'httpMethod' => 'GET',
                            'responseModel' => 'j',
                        ],
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
                                                    'type' => 'number',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'additionalProperties' => [
                                'location' => 'json',
                                'type' => 'string',
                                'filters' => ['strtoupper'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                [
                    'operations' => [
                        'foo' => [
                            'uri' => 'http://httpbin.org',
                            'httpMethod' => 'GET',
                            'responseModel' => 'j',
                        ],
                    ],
                    'models' => [
                        'j' => [
                            'type' => 'object',
                            'location' => 'json',
                            'properties' => [
                                'nested' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'foo' => ['type' => 'string'],
                                        'bar' => ['type' => 'number'],
                                        'bam' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'abc' => [
                                                    'type' => 'number',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'additionalProperties' => [
                                'type' => 'string',
                                'filters' => ['strtoupper'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider nestedProvider
     *
     * @group ResponseLocation
     */
    public function testVisitsNestedProperties(array $desc): void
    {
        $json = [
            'nested' => [
                'foo' => 'abc',
                'bar' => 123,
                'bam' => [
                    'abc' => 456,
                ],
            ],
            'baz' => 'boo',
        ];
        $body = Utils::jsonEncode($json);
        $response = new Response(200, ['Content-Type' => 'application/json'], $body);
        $mock = new MockHandler([$response]);

        $httpClient = new Client(['handler' => $mock]);

        $description = new Description($desc);
        $guzzle = new GuzzleClient($httpClient, $description);
        /** @var ResultInterface */
        $result = $guzzle->foo();
        $expected = [
            'nested' => [
                'foo' => 'abc',
                'bar' => 123,
                'bam' => [
                    'abc' => 456,
                ],
            ],
            'baz' => 'BOO',
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * @group ResponseLocation
     */
    public function testVisitsNullResponseProperties(): void
    {
        $json = [
            'data' => [
                'link' => null,
            ],
        ];

        $body = Utils::jsonEncode($json);
        $response = new Response(200, ['Content-Type' => 'application/json'], $body);
        $mock = new MockHandler([$response]);

        $httpClient = new Client(['handler' => $mock]);

        $description = new Description(
            [
                'operations' => [
                    'foo' => [
                        'uri' => 'http://httpbin.org',
                        'httpMethod' => 'GET',
                        'responseModel' => 'j',
                    ],
                ],
                'models' => [
                    'j' => [
                        'type' => 'object',
                        'location' => 'json',
                        'properties' => [
                            'scalar' => ['type' => 'string'],
                            'data' => [
                                'type' => 'object',
                                'location' => 'json',
                                'properties' => [
                                    'link' => [
                                        'name' => 'val',
                                        'type' => 'string',
                                        'location' => 'json',
                                    ],
                                ],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                ],
            ]
        );
        $guzzle = new GuzzleClient($httpClient, $description);
        /** @var ResultInterface */
        $result = $guzzle->foo();

        $expected = [
            'data' => [
                'link' => null,
            ],
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * @group ResponseLocation
     */
    public function testVisitsNestedArrayOfArrays(): void
    {
        $json = [
            'scalar' => 'foo',
            'nested' => [
                [
                    'bar' => 123,
                    'baz' => false,
                ],
                [
                    'bar' => 345,
                    'baz' => true,
                ],
                [
                    'bar' => 678,
                    'baz' => true,
                ],
            ],
        ];

        $body = Utils::jsonEncode($json);
        $response = new Response(200, ['Content-Type' => 'application/json'], $body);
        $mock = new MockHandler([$response]);

        $httpClient = new Client(['handler' => $mock]);

        $description = new Description([
            'operations' => [
                'foo' => [
                    'uri' => 'http://httpbin.org',
                    'httpMethod' => 'GET',
                    'responseModel' => 'j',
                ],
            ],
            'models' => [
                'j' => [
                    'type' => 'object',
                    'properties' => [
                        'scalar' => [
                            // for some reason (probably because location is also set on array of arrays)
                            // array of arrays sibling elements must have location set to `json`
                            // otherwise JsonLocation ignores them
                            'location' => 'json',
                            'type' => 'string',
                        ],
                        'nested' => [
                            // array of arrays type must be set to `array`
                            // without that JsonLocation throws an exception
                            'type' => 'array',
                            // for array of arrays `location` must be set to `json`
                            // otherwise JsonLocation returns an empty array
                            'location' => 'json',
                            'items' => [
                                // although this is array of arrays, array items type
                                // must be set as `object`
                                'type' => 'object',
                                'properties' => [
                                    'bar' => [
                                        'type' => 'integer',
                                    ],
                                    'baz' => [
                                        'type' => 'boolean',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $guzzle = new GuzzleClient($httpClient, $description);
        /** @var ResultInterface */
        $result = $guzzle->foo();
        $expected = [
            'scalar' => 'foo',
            'nested' => [
                [
                    'bar' => 123,
                    'baz' => false,
                ],
                [
                    'bar' => 345,
                    'baz' => true,
                ],
                [
                    'bar' => 678,
                    'baz' => true,
                ],
            ],
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * @group ResponseLocation
     */
    public function testVisitsNestedArrayOfObjects(): void
    {
        $json = json_decode('{"scalar":"foo","nested":[{"bar":123,"baz":false},{"bar":345,"baz":true},{"bar":678,"baz":true}]}');

        $body = Utils::jsonEncode($json);
        $response = new Response(200, ['Content-Type' => 'application/json'], $body);
        $mock = new MockHandler([$response]);

        $httpClient = new Client(['handler' => $mock]);

        $description = new Description([
            'operations' => [
                'foo' => [
                    'uri' => 'http://httpbin.org',
                    'httpMethod' => 'GET',
                    'responseModel' => 'j',
                ],
            ],
            'models' => [
                'j' => [
                    'type' => 'object',
                    'location' => 'json',
                    'properties' => [
                        'scalar' => [
                            'type' => 'string',
                        ],
                        'nested' => [
                            // array of objects type must be set to `array`
                            // without that JsonLocation throws an exception
                            'type' => 'array',
                            'items' => [
                                // array elements type must be set to `object`
                                'type' => 'object',
                                'properties' => [
                                    'bar' => [
                                        'type' => 'integer',
                                    ],
                                    'baz' => [
                                        'type' => 'boolean',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $guzzle = new GuzzleClient($httpClient, $description);
        /** @var ResultInterface */
        $result = $guzzle->foo();
        $expected = [
            'scalar' => 'foo',
            'nested' => [
                [
                    'bar' => 123,
                    'baz' => false,
                ],
                [
                    'bar' => 345,
                    'baz' => true,
                ],
                [
                    'bar' => 678,
                    'baz' => true,
                ],
            ],
        ];
        $this->assertEquals($expected, $result->toArray());
    }
}
