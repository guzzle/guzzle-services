<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Command\Guzzle\Handler;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\Handler\ValidatedDescriptionHandler;
use GuzzleHttp\Command\Guzzle\SchemaValidator;
use GuzzleHttp\Command\Result;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Server\Server;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Command\Guzzle\Handler\ValidatedDescriptionHandler
 */
class ValidatedDescriptionHandlerTest extends TestCase
{
    public function testValidates(): void
    {
        $this->expectExceptionMessage('Validation errors: [bar] is a required string');
        $this->expectException(CommandException::class);
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

    public function testSuccessfulValidationDoesNotThrow(): void
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

    public function testValidatesAdditionalParameters(): void
    {
        $this->expectExceptionMessage('Validation errors: [bar] must be of type string');
        $this->expectException(CommandException::class);
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

    public function testEscapesCompleteValidationErrorsAtExceptionBoundary(): void
    {
        $validator = new SchemaValidator();
        $description = new Description([
            'operations' => [
                'foo' => [
                    'parameters' => [
                        "bad\0" => [
                            'type' => 'string',
                            'required' => true,
                        ],
                        "worse\n" => [
                            'type' => 'string',
                            'required' => true,
                        ],
                    ],
                ],
            ],
        ]);
        $handler = new ValidatedDescriptionHandler($description, $validator);
        $command = new Command('foo');

        try {
            $handler(static function (CommandInterface $command): PromiseInterface {
                self::fail('Validation should fail before the next handler is called.');
            })($command);
            self::fail('Expected a command exception.');
        } catch (CommandException $e) {
            self::assertSame('Validation errors: [bad\\x00] is a required string; [worse\\x0A] is a required string', $e->getMessage());
            self::assertSame(["[worse\n] is a required string"], $validator->getErrors());
            self::assertSame($command, $e->getCommand());
        }
    }

    public function testFilterBeforeValidate(): void
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

    public function testWritesNormalizedStringableValuesBackToCommand(): void
    {
        $description = new Description([
            'operations' => [
                'foo' => [
                    'uri' => Server::$url,
                    'httpMethod' => 'GET',
                    'parameters' => [
                        'bar' => [
                            'type' => 'string',
                            'location' => 'query',
                        ],
                    ],
                ],
            ],
        ]);

        Server::flush();
        Server::enqueue([new Response(200)]);

        $client = new GuzzleClient(new HttpClient(), $description);
        $command = $client->getCommand('foo', [
            'bar' => new class {
                public function __toString(): string
                {
                    return 'stringable';
                }
            },
        ]);

        $client->execute($command);

        self::assertSame('stringable', $command['bar']);
    }
}
