<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Command\Guzzle\Handler;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Result;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Server\Server;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Command\Guzzle\Handler\ValidatedDescriptionHandler
 */
class ValidatedDescriptionHandlerTest extends TestCase
{
    public function testValidates()
    {
        $this->expectExceptionMessage('Validation errors: [bar] is a required string');
        $this->expectException(\GuzzleHttp\Command\Exception\CommandException::class);
        $description = new Description([
            'operations' => [
                'foo' => [
                    'uri' => Server::$url,
                    'httpMethod' => 'GET',
                    'responseModel' => 'j',
                    'parameters' => [
                        'bar' => [
                            'type' => 'string',
                            'required' => true,
                        ],
                    ],
                ],
            ],
        ]);

        $client = new GuzzleClient(new HttpClient(), $description);
        $client->foo([]);
    }

    public function testSuccessfulValidationDoesNotThrow()
    {
        $description = new Description([
            'operations' => [
                'foo' => [
                    'uri' => Server::$url,
                    'httpMethod' => 'GET',
                    'responseModel' => 'j',
                    'parameters' => [],
                ],
            ],
            'models' => [
                'j' => [
                    'type' => 'object',
                ],
            ],
        ]);

        Server::flush();
        Server::enqueue([new Response(200)]);

        $client = new GuzzleClient(new HttpClient(), $description);
        self::assertInstanceOf(Result::class, $client->foo([]));
    }

    public function testValidatesAdditionalParameters()
    {
        $this->expectExceptionMessage('Validation errors: [bar] must be of type string');
        $this->expectException(\GuzzleHttp\Command\Exception\CommandException::class);
        $description = new Description([
            'operations' => [
                'foo' => [
                    'uri' => Server::$url,
                    'httpMethod' => 'GET',
                    'responseModel' => 'j',
                    'additionalParameters' => [
                        'type' => 'string',
                    ],
                ],
            ],
            'models' => [
                'j' => [
                    'type' => 'object',
                ],
            ],
        ]);

        $client = new GuzzleClient(new HttpClient(), $description);
        $client->foo(['bar' => new \stdClass()]);
    }

    public function testFilterBeforeValidate()
    {
        $description = new Description([
            'operations' => [
                'foo' => [
                    'uri' => Server::$url,
                    'httpMethod' => 'GET',
                    'parameters' => [
                        'bar' => [
                            'location' => 'uri',
                            'type' => 'string',
                            'format' => 'date-time',
                            'required' => true,
                        ],
                    ],
                ],
            ],
        ]);

        Server::flush();
        Server::enqueue([new Response(200)]);

        $client = new GuzzleClient(new HttpClient(), $description);
        // Should not throw any exception
        self::assertInstanceOf(Result::class, $client->foo(['bar' => new \DateTimeImmutable()]));
    }
}
