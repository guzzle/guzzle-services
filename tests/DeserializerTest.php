<?php
namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

/**
 * @covers \GuzzleHttp\Command\Guzzle\Deserializer
 */
class DeserializerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \GuzzleHttp\Command\Exception\CommandException
     * @expectedExceptionMessage 404
     */
    public function testDoesNotAddResultWhenExceptionIsPresent()
    {
        $this->markTestIncomplete('Figure out what this test does');
        $description = new Description([
            'operations' => [
                'foo' => [
                    'uri' => 'http://httpbin.org/{foo}',
                    'httpMethod' => 'GET',
                    'responseModel' => 'j',
                    'parameters' => [
                        'bar' => [
                            'type'     => 'string',
                            'required' => true,
                            'location' => 'uri'
                        ]
                    ]
                ]
            ],
            'models' => [
                'j' => [
                    'type' => 'object'
                ]
            ]
        ]);

        $mock = new MockHandler([new Response(404)]);
        $httpClient = new HttpClient(['handler' => $mock]);
        $client = new GuzzleClient($httpClient, $description);
        $client->foo(['bar' => 'baz']);
    }


    public function testReturnsExpectedResult()
    {
        $loginResponse = new Response(200, [], '{"LoginResponse":{"result":{"type":4,"username":{"uid":38664492,"content":"skyfillers-api-test"},"token":"3FB1F21014D630481D35CBC30CBF4043"},"status":{"code":200,"content":"OK"}}}');
        $mock = new MockHandler([$loginResponse]);

        $description = new Description([
            'name' => 'Test API',
            'baseUrl' => 'http://httpbin.org',
            'operations' => [
                'Login' => [
                    'uri' => '/{foo}',
                    'httpMethod' => 'POST',
                    'responseClass' => 'LoginResponse',
                    'parameters' => [
                        'username' => [
                            'type'     => 'string',
                            'required' => true,
                            'description' => 'Unique user name (alphanumeric)',
                            'location' => 'json'
                        ],
                        'password' => [
                            'type'     => 'string',
                            'required' => true,
                            'description' => 'User\'s password',
                            'location' => 'json'
                        ],
                        'response' => [
                            'type'     => 'string',
                            'required' => false,
                            'description' => 'Determines the response type: xml = result content will be xml formatted (default); plain = result content will be simple text, without structure; json  = result content will be json formatted',
                            'location' => 'json'
                        ],
                        'token' => [
                            'type'     => 'string',
                            'required' => false,
                            'description' => 'Provides the authentication token',
                            'location' => 'json'
                        ]
                    ]
                ]
            ],
            'models' => [
                'LoginResponse' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'location' => 'json'
                    ]
                ]
            ]
        ]);

        $httpClient = new HttpClient(['handler' => $mock]);
        $client = new GuzzleClient($httpClient, $description);
        $result = $client->Login([
            'username' => 'test',
            'password' => 'test',
            'response' => 'json',
        ]);

        $expected = [
            'result' => [
                'type' => 4,
                'username' => [
                    'uid' => 38664492,
                    'content' => 'skyfillers-api-test'
                ],
                'token' => '3FB1F21014D630481D35CBC30CBF4043'
            ],
            'status' => [
                'code' => 200,
                'content' => 'OK'
            ]
        ];
        $this->assertArraySubset($expected, $result['LoginResponse']);
    }
}
