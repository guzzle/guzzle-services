<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\ResponseLocation\XmlLocation;
use GuzzleHttp\Command\Result;
use GuzzleHttp\Command\ResultInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Server\Server;
use GuzzleHttp\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @covers \GuzzleHttp\Command\Guzzle\GuzzleClient
 */
class GuzzleClientTest extends TestCase
{
    public function testExecuteWithQueryLocation(): void
    {
        $mock = new MockHandler();
        $client = $this->getServiceClient(
            [
                new Response(200, [], '{"foo":"bar"}'),
                new Response(200, [], '{"foo":"bar"}'),
            ],
            $mock
        );

        $client->doQueryLocation(['foo' => 'Foo']);
        $this->assertEquals('foo=Foo', $mock->getLastRequest()->getUri()->getQuery());

        $client->doQueryLocation([
            'foo' => 'Foo',
            'bar' => 'Bar',
            'baz' => 'Baz',
        ]);
        $last = $mock->getLastRequest();
        $this->assertEquals('foo=Foo&bar=Bar&baz=Baz', $last->getUri()->getQuery());
    }

    public function testExecuteWithBodyLocation(): void
    {
        $mock = new MockHandler();

        $client = $this->getServiceClient(
            [
                new Response(200, [], '{"foo":"bar"}'),
                new Response(200, [], '{"foo":"bar"}'),
            ],
            $mock
        );

        $client->doBodyLocation(['foo' => 'Foo']);
        $this->assertEquals('foo=Foo', (string) $mock->getLastRequest()->getBody());

        $client->doBodyLocation([
            'foo' => 'Foo',
            'bar' => 'Bar',
            'baz' => 'Baz',
        ]);
        $this->assertEquals('foo=Foo&bar=Bar&baz=Baz', (string) $mock->getLastRequest()->getBody());
    }

    public function testExecuteWithJsonLocation(): void
    {
        $mock = new MockHandler();

        $client = $this->getServiceClient(
            [
                new Response(200, [], '{"foo":"bar"}'),
                new Response(200, [], '{"foo":"bar"}'),
            ],
            $mock
        );

        $client->doJsonLocation(['foo' => 'Foo']);
        $this->assertEquals('{"foo":"Foo"}', (string) $mock->getLastRequest()->getBody());

        $client->doJsonLocation([
            'foo' => 'Foo',
            'bar' => 'Bar',
            'baz' => 'Baz',
        ]);
        $this->assertEquals('{"foo":"Foo","bar":"Bar","baz":"Baz"}', (string) $mock->getLastRequest()->getBody());
    }

    public function testExecuteWithHeaderLocation(): void
    {
        $mock = new MockHandler();

        $client = $this->getServiceClient(
            [
                new Response(200, [], '{"foo":"bar"}'),
                new Response(200, [], '{"foo":"bar"}'),
            ],
            $mock
        );

        $client->doHeaderLocation(['foo' => 'Foo']);
        $this->assertEquals(['Foo'], $mock->getLastRequest()->getHeader('foo'));

        $client->doHeaderLocation([
            'foo' => 'Foo',
            'bar' => 'Bar',
            'baz' => 'Baz',
        ]);
        $this->assertEquals(['Foo'], $mock->getLastRequest()->getHeader('foo'));
        $this->assertEquals(['Bar'], $mock->getLastRequest()->getHeader('bar'));
        $this->assertEquals(['Baz'], $mock->getLastRequest()->getHeader('baz'));
    }

    public function testExecuteWithXmlLocation(): void
    {
        $mock = new MockHandler();

        $client = $this->getServiceClient(
            [
                new Response(200, [], '{"foo":"bar"}'),
                new Response(200, [], '{"foo":"bar"}'),
            ],
            $mock
        );

        $client->doXmlLocation(['foo' => 'Foo']);
        $this->assertEquals(
            "<?xml version=\"1.0\"?>\n<Request><foo>Foo</foo></Request>\n",
            (string) $mock->getLastRequest()->getBody()
        );

        $client->doXmlLocation([
            'foo' => 'Foo',
            'bar' => 'Bar',
            'baz' => 'Baz',
        ]);
        $this->assertEquals(
            "<?xml version=\"1.0\"?>\n<Request><foo>Foo</foo><bar>Bar</bar><baz>Baz</baz></Request>\n",
            (string) $mock->getLastRequest()->getBody()
        );
    }

    public function testPassesConfiguredResponseLocationsToDeserializer(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '<root><value>ok</value></root>'),
        ]);
        $description = new Description([
            'baseUri' => 'http://httpbin.org',
            'operations' => [
                'getXml' => [
                    'httpMethod' => 'GET',
                    'uri' => '/xml',
                    'responseModel' => 'XmlResponse',
                ],
            ],
            'models' => [
                'XmlResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'value' => [
                            'type' => 'string',
                            'location' => 'xml',
                        ],
                    ],
                ],
            ],
        ]);
        $client = new GuzzleClient(
            new HttpClient(['handler' => $mock]),
            $description,
            null,
            null,
            null,
            ['response_locations' => ['xml' => new XmlLocation('xml', 1)]]
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('XML response exceeds maximum depth of 1');

        $client->getXml();
    }

    public function testExecuteWithMultiPartLocation(): void
    {
        $mock = new MockHandler();

        $client = $this->getServiceClient(
            [
                new Response(200, [], '{"foo":"bar"}'),
                new Response(200, [], '{"foo":"bar"}'),
                new Response(200, [], '{"foo":"bar"}'),
            ],
            $mock
        );

        $client->doMultiPartLocation(['foo' => 'Foo']);
        $multiPartRequestBody = (string) $mock->getLastRequest()->getBody();
        $this->assertStringContainsString('name="foo"', $multiPartRequestBody);
        $this->assertStringContainsString('Foo', $multiPartRequestBody);

        $client->doMultiPartLocation([
            'foo' => 'Foo',
            'bar' => 'Bar',
            'baz' => 'Baz',
        ]);

        $multiPartRequestBody = (string) $mock->getLastRequest()->getBody();
        $this->assertStringContainsString('name="foo"', $multiPartRequestBody);
        $this->assertStringContainsString('Foo', $multiPartRequestBody);
        $this->assertStringContainsString('name="bar"', $multiPartRequestBody);
        $this->assertStringContainsString('Bar', $multiPartRequestBody);
        $this->assertStringContainsString('name="baz"', $multiPartRequestBody);
        $this->assertStringContainsString('Baz', $multiPartRequestBody);

        $client->doMultiPartLocation([
            'file' => fopen(dirname(__FILE__).'/Asset/test.html', 'r'),
        ]);
        $multiPartRequestBody = (string) $mock->getLastRequest()->getBody();
        $this->assertStringContainsString('name="file"', $multiPartRequestBody);
        $this->assertStringContainsString('filename="test.html"', $multiPartRequestBody);
        $this->assertStringContainsString('<title>Title</title>', $multiPartRequestBody);
    }

    public function testHasConfig(): void
    {
        $client = new HttpClient();
        $description = new Description([]);
        $guzzle = new GuzzleClient(
            $client,
            $description,
            $this->commandToRequestTransformer(),
            $this->responseToResultTransformer(),
            null,
            ['foo' => 'bar']
        );

        $this->assertSame($client, $guzzle->getHttpClient());
        $this->assertSame($description, $guzzle->getDescription());
        $this->assertEquals('bar', $guzzle->getConfig('foo'));
        $this->assertEquals([], $guzzle->getConfig('defaults'));
        $guzzle->setConfig('abc', 'listen');
        $this->assertEquals('listen', $guzzle->getConfig('abc'));
    }

    /**
     * @dataProvider invalidConfigProvider
     */
    public function testRejectsInvalidConfigOptions(array $config, string $expectedMessage): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new GuzzleClient(
            new HttpClient(),
            new Description([]),
            $this->commandToRequestTransformer(),
            $this->responseToResultTransformer(),
            null,
            $config
        );
    }

    public static function invalidConfigProvider(): iterable
    {
        yield 'defaults' => [
            ['defaults' => 'invalid'],
            'Passing string to GuzzleClient config option "defaults" is invalid; expected array.',
        ];

        yield 'validate' => [
            ['validate' => 'true'],
            'Passing string to GuzzleClient config option "validate" is invalid; expected bool.',
        ];

        yield 'process' => [
            ['process' => 'false'],
            'Passing string to GuzzleClient config option "process" is invalid; expected bool.',
        ];

        yield 'response_locations' => [
            ['response_locations' => 'json'],
            'Passing string to GuzzleClient config option "response_locations" is invalid; expected array.',
        ];

        yield 'response_locations value' => [
            ['response_locations' => ['json' => new \stdClass()]],
            'Passing stdClass to GuzzleClient config option "response_locations.json" is invalid; expected GuzzleHttp\Command\Guzzle\ResponseLocation\ResponseLocationInterface.',
        ];
    }

    public function testRejectsInvalidConfigOptionSetAfterConstruction(): void
    {
        $guzzle = new GuzzleClient(
            new HttpClient(),
            new Description([]),
            $this->commandToRequestTransformer(),
            $this->responseToResultTransformer(),
            null,
            ['validate' => false, 'process' => false]
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Passing string to GuzzleClient config option "process" is invalid; expected bool.');

        $guzzle->setConfig('process', 'false');
    }

    public function testGetCommandUsesExactOperationName(): void
    {
        $guzzle = new GuzzleClient(
            new HttpClient(),
            new Description(['operations' => ['Foo' => []]]),
            $this->commandToRequestTransformer(),
            $this->responseToResultTransformer(),
            null,
            ['validate' => false, 'process' => false]
        );

        $command = $guzzle->getCommand('Foo', ['bar' => 'baz']);

        $this->assertSame('Foo', $command->getName());
        $this->assertSame('baz', $command['bar']);
    }

    public function testGetCommandFallsBackToUcfirstOperationName(): void
    {
        $guzzle = new GuzzleClient(
            new HttpClient(),
            new Description(['operations' => ['Foo' => []]]),
            $this->commandToRequestTransformer(),
            $this->responseToResultTransformer(),
            null,
            ['validate' => false, 'process' => false]
        );

        $command = $guzzle->getCommand('foo');

        $this->assertSame('Foo', $command->getName());
    }

    public function testGetCommandMergesDefaultsWithoutOverwritingExplicitArgs(): void
    {
        $guzzle = new GuzzleClient(
            new HttpClient(),
            new Description(['operations' => ['Foo' => []]]),
            $this->commandToRequestTransformer(),
            $this->responseToResultTransformer(),
            null,
            [
                'defaults' => [
                    'bar' => 'default',
                    'baz' => 'default',
                ],
                'validate' => false,
                'process' => false,
            ]
        );

        $command = $guzzle->getCommand('Foo', ['bar' => 'explicit']);

        $this->assertSame('explicit', $command['bar']);
        $this->assertSame('default', $command['baz']);
    }

    public function testAddsValidateHandlerWhenTrue(): void
    {
        $client = new HttpClient([
            'handler' => new MockHandler([new Response(200, [], '{"ok":true}')]),
        ]);
        $description = new Description([
            'operations' => [
                'Foo' => [
                    'parameters' => [
                        'bar' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                    ],
                ],
            ],
        ]);
        $guzzle = new GuzzleClient(
            $client,
            $description,
            $this->commandToRequestTransformer(),
            $this->responseToResultTransformer(),
            null,
            [
                'validate' => true,
                'process' => false,
            ]
        );

        $this->expectException(CommandException::class);
        $this->expectExceptionMessage('Validation errors: [bar] is a required string');

        $guzzle->execute($guzzle->getCommand('Foo'));
    }

    public function testSkipsValidationWhenFalse(): void
    {
        $client = new HttpClient([
            'handler' => new MockHandler([new Response(200, [], '{"ok":true}')]),
        ]);
        $description = new Description([
            'operations' => [
                'Foo' => [
                    'parameters' => [
                        'bar' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                    ],
                ],
            ],
        ]);
        $guzzle = new GuzzleClient(
            $client,
            $description,
            $this->commandToRequestTransformer(),
            $this->responseToResultTransformer(),
            null,
            [
                'validate' => false,
                'process' => false,
            ]
        );

        $result = $guzzle->execute($guzzle->getCommand('Foo'));

        $this->assertSame(true, $result['ok']);
        $this->assertSame('Foo', $result['_request']['action']);
    }

    public function testValidateDescription(): void
    {
        $client = new HttpClient();
        $description = new Description(
            [
                'name' => 'Testing API ',
                'baseUri' => Server::$url,
                'operations' => [
                    'Foo' => [
                        'httpMethod' => 'GET',
                        'uri' => '/get',
                        'parameters' => [
                            'bar' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Bar',
                                'location' => 'query',
                            ],
                            'baz' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'baz',
                                'location' => 'query',
                            ],
                        ],
                        'responseModel' => 'Foo',
                    ],
                ],
                'models' => [
                    'Foo' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'location' => 'json',
                                'type' => 'string',
                            ],
                            'location' => [
                                'location' => 'header',
                                'sentAs' => 'Location',
                                'type' => 'string',
                            ],
                            'age' => [
                                'location' => 'json',
                                'type' => 'integer',
                            ],
                            'statusCode' => [
                                'location' => 'statusCode',
                                'type' => 'integer',
                            ],
                        ],
                    ],
                ],
            ]
        );

        Server::flush();
        Server::enqueue([new Response(200)]);

        $guzzle = new GuzzleClient(
            $client,
            $description,
            null,
            null,
            null,
            [
                'validate' => true,
                'process' => false,
            ]
        );

        $command = $guzzle->getCommand('Foo', ['baz' => 'BAZ']);
        /** @var ResultInterface */
        $result = $guzzle->execute($command);
        $response = $result['response'];
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $requests = Server::received();
        $this->assertCount(1, $requests);
        $query = [];
        parse_str($requests[0]->getUri()->getQuery(), $query);
        $this->assertSame('BAZ', $query['baz']);
    }

    public function testValidateDescriptionFailsDueMissingRequiredParameter(): void
    {
        $this->expectExceptionMessage('Validation errors: [baz] is a required string: baz');
        $this->expectException(CommandException::class);
        $client = new HttpClient();
        $description = new Description(
            [
                'name' => 'Testing API ',
                'baseUri' => 'http://httpbin.org/',
                'operations' => [
                    'Foo' => [
                        'httpMethod' => 'GET',
                        'uri' => '/get',
                        'parameters' => [
                            'bar' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Bar',
                                'location' => 'query',
                            ],
                            'baz' => [
                                'type' => 'string',
                                'required' => true,
                                'description' => 'baz',
                                'location' => 'query',
                            ],
                        ],
                        'responseModel' => 'Foo',
                    ],
                ],
                'models' => [
                    'Foo' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'location' => 'json',
                                'type' => 'string',
                            ],
                            'location' => [
                                'location' => 'header',
                                'sentAs' => 'Location',
                                'type' => 'string',
                            ],
                            'age' => [
                                'location' => 'json',
                                'type' => 'integer',
                            ],
                            'statusCode' => [
                                'location' => 'statusCode',
                                'type' => 'integer',
                            ],
                        ],
                    ],
                ],
            ]
        );

        $guzzle = new GuzzleClient(
            $client,
            $description,
            null,
            null,
            null,
            [
                'validate' => true,
                'process' => false,
            ]
        );

        $command = $guzzle->getCommand('Foo');
        /** @var ResultInterface */
        $result = $guzzle->execute($command);
        $this->assertInstanceOf(Result::class, $result);
        $result = $result->toArray();
        $this->assertEquals(200, $result['statusCode']);
    }

    public function testValidateDescriptionFailsDueTypeMismatch(): void
    {
        $this->expectExceptionMessage('Validation errors: [baz] must be of type integer');
        $this->expectException(CommandException::class);
        $client = new HttpClient();
        $description = new Description(
            [
                'name' => 'Testing API ',
                'baseUri' => 'http://httpbin.org/',
                'operations' => [
                    'Foo' => [
                        'httpMethod' => 'GET',
                        'uri' => '/get',
                        'parameters' => [
                            'bar' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Bar',
                                'location' => 'query',
                            ],
                            'baz' => [
                                'type' => 'integer',
                                'required' => true,
                                'description' => 'baz',
                                'location' => 'query',
                            ],
                        ],
                        'responseModel' => 'Foo',
                    ],
                ],
                'models' => [
                    'Foo' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'location' => 'json',
                                'type' => 'string',
                            ],
                            'location' => [
                                'location' => 'header',
                                'sentAs' => 'Location',
                                'type' => 'string',
                            ],
                            'age' => [
                                'location' => 'json',
                                'type' => 'integer',
                            ],
                            'statusCode' => [
                                'location' => 'statusCode',
                                'type' => 'integer',
                            ],
                        ],
                    ],
                ],
            ]
        );

        $guzzle = new GuzzleClient(
            $client,
            $description,
            null,
            null,
            null,
            [
                'validate' => true,
                'process' => false,
            ]
        );

        $command = $guzzle->getCommand('Foo', ['baz' => 'Hello']);
        /** @var ResultInterface */
        $result = $guzzle->execute($command);
        $this->assertInstanceOf(Result::class, $result);
        $result = $result->toArray();
        $this->assertEquals(200, $result['statusCode']);
    }

    public function testValidateDescriptionDoesNotFailWhenSendingIntegerButExpectingString(): void
    {
        $client = new HttpClient();
        $description = new Description(
            [
                'name' => 'Testing API ',
                'baseUri' => Server::$url,
                'operations' => [
                    'Foo' => [
                        'httpMethod' => 'GET',
                        'uri' => '/get',
                        'parameters' => [
                            'bar' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Bar',
                                'location' => 'query',
                            ],
                            'baz' => [
                                'type' => 'string',
                                'required' => true,
                                'description' => 'baz',
                                'location' => 'query',
                            ],
                        ],
                        'responseModel' => 'Foo',
                    ],
                ],
                'models' => [
                    'Foo' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'location' => 'json',
                                'type' => 'string',
                            ],
                            'location' => [
                                'location' => 'header',
                                'sentAs' => 'Location',
                                'type' => 'string',
                            ],
                            'age' => [
                                'location' => 'json',
                                'type' => 'integer',
                            ],
                            'statusCode' => [
                                'location' => 'statusCode',
                                'type' => 'integer',
                            ],
                        ],
                    ],
                ],
            ]
        );

        Server::flush();
        Server::enqueue([new Response(200)]);

        $guzzle = new GuzzleClient($client, $description);

        $command = $guzzle->getCommand('Foo', ['baz' => 42]);
        /** @var ResultInterface */
        $result = $guzzle->execute($command);
        $this->assertInstanceOf(Result::class, $result);
        $result = $result->toArray();
        $this->assertEquals(200, $result['statusCode']);

        $requests = Server::received();
        $this->assertCount(1, $requests);
        $query = [];
        parse_str($requests[0]->getUri()->getQuery(), $query);
        $this->assertSame('42', $query['baz']);
    }

    public function testThrowsWhenOperationNotFoundInDescription(): void
    {
        $this->expectExceptionMessage('No operation found named Foo');
        $this->expectException(\InvalidArgumentException::class);
        $client = new HttpClient();
        $description = new Description([]);
        $guzzle = new GuzzleClient(
            $client,
            $description,
            $this->commandToRequestTransformer(),
            $this->responseToResultTransformer()
        );
        $guzzle->getCommand('foo');
    }

    public function testReturnsProcessedResponse(): void
    {
        $client = new HttpClient();

        $description = new Description(
            [
                'name' => 'Testing API ',
                'baseUri' => Server::$url,
                'operations' => [
                    'Foo' => [
                        'httpMethod' => 'GET',
                        'uri' => '/get',
                        'parameters' => [
                            'bar' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Bar',
                                'location' => 'query',
                            ],
                            'baz' => [
                                'type' => 'string',
                                'required' => true,
                                'description' => 'baz',
                                'location' => 'query',
                            ],
                        ],
                        'responseModel' => 'Foo',
                    ],
                ],
                'models' => [
                    'Foo' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'location' => 'json',
                                'type' => 'string',
                            ],
                            'location' => [
                                'location' => 'header',
                                'sentAs' => 'Location',
                                'type' => 'string',
                            ],
                            'age' => [
                                'location' => 'json',
                                'type' => 'integer',
                            ],
                            'statusCode' => [
                                'location' => 'statusCode',
                                'type' => 'integer',
                            ],
                        ],
                    ],
                ],
            ]
        );

        Server::flush();
        Server::enqueue([new Response(200)]);

        $guzzle = new GuzzleClient($client, $description, null, null);
        $command = $guzzle->getCommand('foo', ['baz' => 'BAZ']);

        /** @var ResultInterface */
        $result = $guzzle->execute($command);
        $this->assertInstanceOf(Result::class, $result);
        $result = $result->toArray();
        $this->assertEquals(200, $result['statusCode']);

        $requests = Server::received();
        $this->assertCount(1, $requests);
        $query = [];
        parse_str($requests[0]->getUri()->getQuery(), $query);
        $this->assertSame('BAZ', $query['baz']);
    }

    /**
     * @param array<array-key, mixed>                             $responses
     * @param (callable(CommandInterface): RequestInterface)|null $commandToRequestTransformer
     */
    private function getServiceClient(
        array $responses,
        ?MockHandler $mock = null,
        ?callable $commandToRequestTransformer = null
    ): GuzzleClient {
        $mock = $mock ?: new MockHandler();

        foreach ($responses as $response) {
            $mock->append($response);
        }

        return new GuzzleClient(
            new HttpClient([
                'handler' => $mock,
            ]),
            $this->getDescription(),
            $commandToRequestTransformer,
            $this->responseToResultTransformer(),
            null,
            ['foo' => 'bar']
        );
    }

    /**
     * @return callable(CommandInterface): RequestInterface
     */
    private function commandToRequestTransformer(): callable
    {
        return function (CommandInterface $command): Request {
            $data = $command->toArray();
            $data['action'] = $command->getName();

            return new Request('POST', '/', [], http_build_query($data));
        };
    }

    /**
     * @return callable(ResponseInterface, RequestInterface, CommandInterface): ResultInterface
     */
    private function responseToResultTransformer(): callable
    {
        return function (ResponseInterface $response, RequestInterface $request, CommandInterface $command): Result {
            $data = Utils::jsonDecode((string) $response->getBody(), true);
            parse_str((string) $request->getBody(), $data['_request']);

            return new Result($data);
        };
    }

    private function getDescription(): Description
    {
        return new Description(
            [
                'name' => 'Testing API ',
                'baseUri' => 'http://httpbin.org/',
                'operations' => [
                    'doThatThingYouDo' => [
                        'responseModel' => 'Bar',
                    ],
                    'doThatThingOtherYouDo' => [
                        'responseModel' => 'Foo',
                    ],
                    'doQueryLocation' => [
                        'httpMethod' => 'GET',
                        'uri' => '/queryLocation',
                        'parameters' => [
                            'foo' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing query request location',
                                'location' => 'query',
                            ],
                            'bar' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing query request location',
                                'location' => 'query',
                            ],
                            'baz' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing query request location',
                                'location' => 'query',
                            ],
                        ],
                        'responseModel' => 'QueryResponse',
                    ],
                    'doBodyLocation' => [
                        'httpMethod' => 'GET',
                        'uri' => '/bodyLocation',
                        'parameters' => [
                            'foo' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing body request location',
                                'location' => 'body',
                            ],
                            'bar' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing body request location',
                                'location' => 'body',
                            ],
                            'baz' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing body request location',
                                'location' => 'body',
                            ],
                        ],
                        'responseModel' => 'BodyResponse',
                    ],
                    'doJsonLocation' => [
                        'httpMethod' => 'GET',
                        'uri' => '/jsonLocation',
                        'parameters' => [
                            'foo' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing json request location',
                                'location' => 'json',
                            ],
                            'bar' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing json request location',
                                'location' => 'json',
                            ],
                            'baz' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing json request location',
                                'location' => 'json',
                            ],
                        ],
                        'responseModel' => 'JsonResponse',
                    ],
                    'doHeaderLocation' => [
                        'httpMethod' => 'GET',
                        'uri' => '/headerLocation',
                        'parameters' => [
                            'foo' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing header request location',
                                'location' => 'header',
                            ],
                            'bar' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing header request location',
                                'location' => 'header',
                            ],
                            'baz' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing header request location',
                                'location' => 'header',
                            ],
                        ],
                        'responseModel' => 'HeaderResponse',
                    ],
                    'doXmlLocation' => [
                        'httpMethod' => 'GET',
                        'uri' => '/xmlLocation',
                        'parameters' => [
                            'foo' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing xml request location',
                                'location' => 'xml',
                            ],
                            'bar' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing xml request location',
                                'location' => 'xml',
                            ],
                            'baz' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing xml request location',
                                'location' => 'xml',
                            ],
                        ],
                        'responseModel' => 'XmlResponse',
                    ],
                    'doMultiPartLocation' => [
                        'httpMethod' => 'POST',
                        'uri' => '/multipartLocation',
                        'parameters' => [
                            'foo' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing multipart request location',
                                'location' => 'multipart',
                            ],
                            'bar' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing multipart request location',
                                'location' => 'multipart',
                            ],
                            'baz' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing multipart request location',
                                'location' => 'multipart',
                            ],
                            'file' => [
                                'type' => 'any',
                                'required' => false,
                                'description' => 'Testing multipart request location',
                                'location' => 'multipart',
                            ],
                        ],
                        'responseModel' => 'MultipartResponse',
                    ],
                ],
                'models' => [
                    'Foo' => [
                        'type' => 'object',
                        'properties' => [
                            'code' => [
                                'location' => 'statusCode',
                            ],
                        ],
                    ],
                    'Bar' => [
                        'type' => 'object',
                        'properties' => [
                            'code' => ['
                                location' => 'statusCode',
                            ],
                        ],
                    ],
                ],
            ]
        );
    }

    public function testDocumentationExampleFromReadme(): void
    {
        $client = new HttpClient();
        $description = new Description([
            'baseUri' => Server::$url,
            'operations' => [
                'testing' => [
                    'httpMethod' => 'GET',
                    'uri' => '/get{?foo}',
                    'responseModel' => 'getResponse',
                    'parameters' => [
                        'foo' => [
                            'type' => 'string',
                            'location' => 'uri',
                        ],
                        'bar' => [
                            'type' => 'string',
                            'location' => 'query',
                        ],
                    ],
                ],
            ],
            'models' => [
                'getResponse' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'location' => 'json',
                    ],
                ],
            ],
        ]);

        $guzzle = new GuzzleClient($client, $description);

        Server::flush();
        Server::enqueue([new Response(200, [], '{"args":{"foo":"bar"}}')]);

        $result = $guzzle->testing(['foo' => 'bar']);
        $this->assertEquals('bar', $result['args']['foo']);

        $requests = Server::received();
        $this->assertCount(1, $requests);
        $query = [];
        parse_str($requests[0]->getUri()->getQuery(), $query);
        $this->assertSame('bar', $query['foo']);
    }

    public function testDescriptionWithExtends(): void
    {
        $client = new HttpClient();
        $description = new Description([
            'baseUri' => Server::$url,
            'operations' => [
                'testing' => [
                    'httpMethod' => 'GET',
                    'uri' => '/get',
                    'responseModel' => 'getResponse',
                    'parameters' => [
                        'foo' => [
                            'type' => 'string',
                            'default' => 'foo',
                            'location' => 'query',
                        ],
                    ],
                ],
                'testing_extends' => [
                    'extends' => 'testing',
                    'responseModel' => 'getResponse',
                    'parameters' => [
                        'bar' => [
                            'type' => 'string',
                            'location' => 'query',
                        ],
                    ],
                ],
            ],
            'models' => [
                'getResponse' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'location' => 'json',
                    ],
                ],
            ],
        ]);
        $guzzle = new GuzzleClient($client, $description);

        Server::flush();
        Server::enqueue([new Response(200, [], '{"args":{"foo":"foo","bar":"bar"}}')]);

        $result = $guzzle->testing_extends(['bar' => 'bar']);
        $this->assertEquals('bar', $result['args']['bar']);
        $this->assertEquals('foo', $result['args']['foo']);

        $requests = Server::received();
        $this->assertCount(1, $requests);
        $query = [];
        parse_str($requests[0]->getUri()->getQuery(), $query);
        $this->assertSame('bar', $query['bar']);
        $this->assertSame('foo', $query['foo']);
    }
}
