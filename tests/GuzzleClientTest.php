<?php
namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Result;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @covers \GuzzleHttp\Command\Guzzle\GuzzleClient
 */
class GuzzleClientTest extends \PHPUnit_Framework_TestCase
{
    public function testExecuteCommandViaMagicMethod()
    {
        $client = $this->getServiceClient(
            [
                new Response(200, [], '{"foo":"bar"}'),
                new Response(200, [], '{"foofoo":"barbar"}'),
            ],
            null,
            $this->commandToRequestTransformer()
        );

        // Synchronous
        $result1 = $client->doThatThingYouDo(['fizz' => 'buzz']);
        $this->assertEquals('bar', $result1['foo']);
        $this->assertEquals('buzz', $result1['_request']['fizz']);
        $this->assertEquals('doThatThingYouDo', $result1['_request']['action']);

        // Asynchronous
        $result2 = $client->doThatThingOtherYouDoAsync(['fizz' => 'buzz'])->wait();
        $this->assertEquals('barbar', $result2['foofoo']);
        $this->assertEquals('doThatThingOtherYouDo', $result2['_request']['action']);
    }

    public function testExecuteWithQueryLocation()
    {
        $mock = new MockHandler();
        $client = $this->getServiceClient(
            [
                new Response(200, [], '{"foo":"bar"}'),
                new Response(200, [], '{"foo":"bar"}')
            ],
            $mock
        );

        $client->doQueryLocation(['foo' => 'Foo']);
        $this->assertEquals('foo=Foo', $mock->getLastRequest()->getUri()->getQuery());

        $client->doQueryLocation([
            'foo' => 'Foo',
            'bar' => 'Bar',
            'baz' => 'Baz'
        ]);
        $last = $mock->getLastRequest();
        $this->assertEquals('foo=Foo&bar=Bar&baz=Baz', $last->getUri()->getQuery());
    }

    public function testExecuteWithBodyLocation()
    {
        $mock = new MockHandler();

        $client = $this->getServiceClient(
            [
                new Response(200, [], '{"foo":"bar"}'),
                new Response(200, [], '{"foo":"bar"}')
            ],
            $mock
        );

        $client->doBodyLocation(['foo' => 'Foo']);
        $this->assertEquals('foo=Foo', (string) $mock->getLastRequest()->getBody());

        $client->doBodyLocation([
            'foo' => 'Foo',
            'bar' => 'Bar',
            'baz' => 'Baz'
        ]);
        $this->assertEquals('foo=Foo&bar=Bar&baz=Baz', (string) $mock->getLastRequest()->getBody());
    }

    public function testExecuteWithJsonLocation()
    {
        $mock = new MockHandler();

        $client = $this->getServiceClient(
            [
                new Response(200, [], '{"foo":"bar"}'),
                new Response(200, [], '{"foo":"bar"}')
            ],
            $mock
        );

        $client->doJsonLocation(['foo' => 'Foo']);
        $this->assertEquals('{"foo":"Foo"}', (string) $mock->getLastRequest()->getBody());

        $client->doJsonLocation([
            'foo' => 'Foo',
            'bar' => 'Bar',
            'baz' => 'Baz'
        ]);
        $this->assertEquals('{"foo":"Foo","bar":"Bar","baz":"Baz"}', (string) $mock->getLastRequest()->getBody());
    }

    public function testExecuteWithHeaderLocation()
    {
        $mock = new MockHandler();

        $client = $this->getServiceClient(
            [
                new Response(200, [], '{"foo":"bar"}'),
                new Response(200, [], '{"foo":"bar"}')
            ],
            $mock
        );

        $client->doHeaderLocation(['foo' => 'Foo']);
        $this->assertEquals(['Foo'], $mock->getLastRequest()->getHeader('foo'));

        $client->doHeaderLocation([
            'foo' => 'Foo',
            'bar' => 'Bar',
            'baz' => 'Baz'
        ]);
        $this->assertEquals(['Foo'], $mock->getLastRequest()->getHeader('foo'));
        $this->assertEquals(['Bar'], $mock->getLastRequest()->getHeader('bar'));
        $this->assertEquals(['Baz'], $mock->getLastRequest()->getHeader('baz'));
    }

    public function testExecuteWithXmlLocation()
    {
        $mock = new MockHandler();

        $client = $this->getServiceClient(
            [
                new Response(200, [], '{"foo":"bar"}'),
                new Response(200, [], '{"foo":"bar"}')
            ],
            $mock
        );

        $client->doXmlLocation(['foo' => 'Foo']);
        $this->assertEquals("<?xml version=\"1.0\"?>\n<Request><foo>Foo</foo></Request>\n", (string) $mock->getLastRequest()->getBody());

        $client->doXmlLocation([
            'foo' => 'Foo',
            'bar' => 'Bar',
            'baz' => 'Baz'
        ]);
        $this->assertEquals("<?xml version=\"1.0\"?>\n<Request><foo>Foo</foo><bar>Bar</bar><baz>Baz</baz></Request>\n", $mock->getLastRequest()->getBody());
    }

    public function testHasConfig()
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

    public function testAddsSubscribersWhenTrue()
    {
        $this->markTestSkipped('Migrate the test to Middelware / Transformers');
        $client = new HttpClient();
        $description = new Description([]);
        $guzzle = new GuzzleClient(
            $client,
            $description,
            $this->commandToRequestTransformer(),
            $this->responseToResultTransformer(),
            null,
            [
                'validate' => true,
                'process' => true
            ]
        );
        $this->assertCount(1, $guzzle->getEmitter()->listeners('process'));
    }

    public function testDisablesSubscribersWhenFalse()
    {
        $this->markTestSkipped('Migrate the test to Middelware / Transformers');
        $client = new HttpClient();
        $description = new Description([]);
        $guzzle = new GuzzleClient($client, $description, [
            'validate' => false,
            'process' => false
        ]);
        $this->assertCount(0, $guzzle->getEmitter()->listeners('process'));
    }

    public function testCanUseCustomConfigFactory()
    {
        $this->markTestIncomplete();
        $mock = $this->getMockBuilder(Command::class)
            ->disableOriginalConstructor()
            ->getMock();

        $client = new HttpClient();
        $description = new Description([]);
        $guzzle = new GuzzleClient(
            $client,
            $description,
            [
            'command_factory' => function () use ($mock) {
                $this->assertCount(3, func_get_args());
                return $mock;
            },
            $this->responseToResultTransformer(),
        ]);
        $this->assertSame($mock, $guzzle->getCommand('foo'));
    }

    public function testMagicMethodExecutesCommands()
    {
        $this->markTestIncomplete();

        $mock = $this->getMockBuilder(Command::class)
            ->setConstructorArgs(['foo'])
            ->getMock();

        $client = new HttpClient();
        $description = new Description([]);

        $guzzle = $this->getMockBuilder(GuzzleClient::class)
            ->setConstructorArgs([
                $client,
                $description,
                [
                    'command_factory' => function ($name) use ($mock) {
                        $this->assertEquals('foo', $name);
                        $this->assertCount(3, func_get_args());
                        return $mock;
                    }
                ],
                $this->responseToResultTransformer(),
            ])
            ->setMethods(['execute'])
            ->getMock();

        $guzzle->expects($this->once())
            ->method('execute')
            ->will($this->returnValue('foo'));

        $this->assertEquals('foo', $guzzle->foo([]));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage No operation found named Foo
     */
    public function testThrowsWhenFactoryReturnsNull()
    {
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

    public function testReturnsProcessedResponse()
    {
        $this->markTestIncomplete();
        $handler = HandlerStack::create();
        $handler->push(Middleware::mapResponse(function (ResponseInterface $response) {
            return $response->withStatus(201);
        }), 'map_response');

//        $client = new Client(['handler' => $handler]);
        $client = new HttpClient();
//        $client->getEmitter()->on('before', function (BeforeEvent $event) {
//            $event->intercept(new Response(201));
//        });

        $description = new Description([
            'operations' => [
                'Foo' => ['responseModel' => 'Bar']
            ],
            'models' => [
                'Bar' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['location' => 'statusCode']
                    ]
                ]
            ]
        ]);

        $guzzle = new GuzzleClient(
            $client,
            $description,
            $this->commandToRequestTransformer(),
            $this->responseToResultTransformer()
        );
        $command = $guzzle->getCommand('foo');
        $result = $guzzle->execute($command);
        $this->assertInternalType('array', $result);
        $this->assertEquals(201, $result['code']);
    }


    private function getServiceClient(array $responses, MockHandler $mock = null, callable $commandToRequestTransformer = null)
    {
        $mock = $mock ?: new MockHandler();

        foreach ($responses as $response) {
            $mock->append($response);
        }

        return new GuzzleClient(
            new HttpClient([
                'handler' => $mock
            ]),
            $this->getDescription(),
            $commandToRequestTransformer,
            $this->responseToResultTransformer(),
            null,
            ['foo' => 'bar']
        );
    }

    private function commandToRequestTransformer()
    {
        return function (CommandInterface $command) {
            $data           = $command->toArray();
            $data['action'] = $command->getName();

            return new Request('POST', '/', [], http_build_query($data));
        };
    }

    private function responseToResultTransformer()
    {
        return function (ResponseInterface $response, RequestInterface $request) {
            $data = \GuzzleHttp\json_decode($response->getBody(), true);
            parse_str($request->getBody(), $data['_request']);

            return new Result($data);
        };
    }

    private function getDescription()
    {
        return new Description(
            [
                'name' => 'Testing API ',
                'baseUrl' => 'http://httpbin.org/',
                'operations' => [
                    'doThatThingYouDo' => [
                        'responseModel' => 'Bar'
                    ],
                    'doThatThingOtherYouDo' => [
                        'responseModel' => 'Foo'
                    ],
                    'doQueryLocation' => [
                        'httpMethod' => 'GET',
                        'uri' => '/queryLocation',
                        'parameters' => [
                            'foo' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing query request location',
                                'location' => 'query'
                            ],
                            'bar' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing query request location',
                                'location' => 'query'
                            ],
                            'baz' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing query request location',
                                'location' => 'query'
                            ]
                        ],
                        'responseModel' => 'QueryResponse'
                    ],
                    'doBodyLocation' => [
                        'httpMethod' => 'GET',
                        'uri' => '/bodyLocation',
                        'parameters' => [
                            'foo' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing body request location',
                                'location' => 'body'
                            ],
                            'bar' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing body request location',
                                'location' => 'body'
                            ],
                            'baz' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing body request location',
                                'location' => 'body'
                            ]
                        ],
                        'responseModel' => 'BodyResponse'
                    ],
                    'doJsonLocation' => [
                        'httpMethod' => 'GET',
                        'uri' => '/jsonLocation',
                        'parameters' => [
                            'foo' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing json request location',
                                'location' => 'json'
                            ],
                            'bar' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing json request location',
                                'location' => 'json'
                            ],
                            'baz' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing json request location',
                                'location' => 'json'
                            ]
                        ],
                        'responseModel' => 'JsonResponse'
                    ],
                    'doHeaderLocation' => [
                        'httpMethod' => 'GET',
                        'uri' => '/headerLocation',
                        'parameters' => [
                            'foo' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing header request location',
                                'location' => 'header'
                            ],
                            'bar' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing header request location',
                                'location' => 'header'
                            ],
                            'baz' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing header request location',
                                'location' => 'header'
                            ]
                        ],
                        'responseModel' => 'HeaderResponse'
                    ],
                    'doXmlLocation' => [
                        'httpMethod' => 'GET',
                        'uri' => '/xmlLocation',
                        'parameters' => [
                            'foo' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing xml request location',
                                'location' => 'xml'
                            ],
                            'bar' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing xml request location',
                                'location' => 'xml'
                            ],
                            'baz' => [
                                'type' => 'string',
                                'required' => false,
                                'description' => 'Testing xml request location',
                                'location' => 'xml'
                            ]
                        ],
                        'responseModel' => 'HeaderResponse'
                    ],
                ],
                'models'  => [
                    'Foo' => [
                        'type' => 'object',
                        'properties' => [
                            'code' => [
                                'location' => 'statusCode'
                            ]
                        ]
                    ],
                    'Bar' => [
                        'type' => 'object',
                        'properties' => [
                            'code' => ['
                                location' => 'statusCode'
                            ]
                        ]
                    ]
                ]
            ]
        );
    }
}
