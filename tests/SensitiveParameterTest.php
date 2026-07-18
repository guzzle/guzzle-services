<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\Deserializer;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\Handler\ValidatedDescriptionHandler;
use GuzzleHttp\Command\Guzzle\NonFiniteFloats;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\QuerySerializer\QuerySerializerInterface;
use GuzzleHttp\Command\Guzzle\QuerySerializer\Rfc3986Serializer;
use GuzzleHttp\Command\Guzzle\RequestLocation\AbstractLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\BodyLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\FormParamLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\HeaderLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\JsonLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\MultiPartLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\QueryLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\RequestLocationInterface;
use GuzzleHttp\Command\Guzzle\RequestLocation\XmlLocation;
use GuzzleHttp\Command\Guzzle\SchemaValidator;
use GuzzleHttp\Command\Guzzle\Serializer;
use GuzzleHttp\Command\Result;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * @covers \GuzzleHttp\Command\Guzzle\Deserializer
 * @covers \GuzzleHttp\Command\Guzzle\GuzzleClient
 * @covers \GuzzleHttp\Command\Guzzle\Handler\ValidatedDescriptionHandler
 * @covers \GuzzleHttp\Command\Guzzle\NonFiniteFloats
 * @covers \GuzzleHttp\Command\Guzzle\Parameter
 * @covers \GuzzleHttp\Command\Guzzle\QuerySerializer\Rfc3986Serializer
 * @covers \GuzzleHttp\Command\Guzzle\RequestLocation\AbstractLocation
 * @covers \GuzzleHttp\Command\Guzzle\SchemaValidator
 * @covers \GuzzleHttp\Command\Guzzle\Serializer
 */
class SensitiveParameterTest extends TestCase
{
    public function testSourceContainsExactSensitiveParameterCount(): void
    {
        self::assertSame(53, self::countSourceAttributes(__DIR__.'/../src'));
    }

    public function testSensitiveParameterInventory(): void
    {
        $attributeCount = 0;
        foreach (self::inventory() as $entry) {
            list($class, $method, $sensitiveParameters) = $entry;
            $reflection = new \ReflectionMethod($class, $method);

            if (\PHP_VERSION_ID < 80000) {
                $parameterNames = array_map(static function (\ReflectionParameter $parameter): string {
                    return $parameter->getName();
                }, $reflection->getParameters());
                foreach ($sensitiveParameters as $sensitiveParameter) {
                    self::assertContains($sensitiveParameter, $parameterNames);
                }
                $attributeCount += count($sensitiveParameters);

                continue;
            }

            foreach ($reflection->getParameters() as $parameter) {
                $attributes = $parameter->getAttributes(\SensitiveParameter::class);
                $expected = in_array($parameter->getName(), $sensitiveParameters, true)
                    ? 1
                    : 0;

                self::assertCount($expected, $attributes, $class.'::'.$method.'::$'.$parameter->getName());
                foreach ($attributes as $attribute) {
                    self::assertInstanceOf(\SensitiveParameter::class, $attribute->newInstance());
                }
                $attributeCount += count($attributes);
            }
        }

        self::assertSame(52, $attributeCount);
    }

    public function testValidatedHandlerClosureParameterIsSensitive(): void
    {
        $middleware = new ValidatedDescriptionHandler(new Description([]));
        $handler = $middleware(static function (CommandInterface $command): PromiseInterface {
            return new FulfilledPromise(new Result());
        });
        $parameters = (new \ReflectionFunction($handler))->getParameters();

        self::assertCount(1, $parameters);
        self::assertSame('command', $parameters[0]->getName());
        if (\PHP_VERSION_ID < 80000) {
            return;
        }
        self::assertCount(1, $parameters[0]->getAttributes(\SensitiveParameter::class));
    }

    public function testInterfacesAndNoOpAbstractMethodsAreNotAnnotated(): void
    {
        if (\PHP_VERSION_ID < 80000) {
            self::markTestSkipped('Attributes are not reflected before PHP 8.0.');
        }

        foreach ([RequestLocationInterface::class, QuerySerializerInterface::class] as $interface) {
            foreach ((new \ReflectionClass($interface))->getMethods() as $method) {
                foreach ($method->getParameters() as $parameter) {
                    self::assertCount(0, $parameter->getAttributes(\SensitiveParameter::class));
                }
            }
        }

        foreach (['visit', 'after'] as $methodName) {
            $method = new \ReflectionMethod(AbstractLocation::class, $methodName);
            foreach ($method->getParameters() as $parameter) {
                self::assertCount(0, $parameter->getAttributes(\SensitiveParameter::class));
            }
        }
    }

    public function testDeserializerErrorArgumentsAreRedactedFromTrace(): void
    {
        if (\PHP_VERSION_ID < 80200) {
            self::markTestSkipped('Native trace redaction requires PHP 8.2.');
        }

        $description = new Description([
            'operations' => [
                'GetSecret' => [
                    'errorResponses' => [
                        ['code' => 401, 'class' => CommandException::class],
                    ],
                ],
            ],
        ]);
        $deserializer = new Deserializer($description, true);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturnCallback(static function (): int {
            throw new \RuntimeException('status failed');
        });
        $request = new Request('GET', '/?api_key=request-secret');
        $command = new Command('GetSecret', ['api_key' => 'command-secret']);

        $previous = ini_get('zend.exception_ignore_args');
        if (ini_set('zend.exception_ignore_args', '0') === false) {
            self::markTestSkipped('Trace arguments cannot be enabled.');
        }

        try {
            $deserializer($response, $request, $command);
            self::fail('Expected a command exception.');
        } catch (\RuntimeException $exception) {
            $invokeFrame = self::findFrame($exception, Deserializer::class, '__invoke');
            self::assertCount(3, $invokeFrame['args']);
            foreach ($invokeFrame['args'] as $argument) {
                self::assertInstanceOf(\SensitiveParameterValue::class, $argument);
            }

            $errorFrame = self::findFrame($exception, Deserializer::class, 'handleErrorResponses');
            self::assertCount(4, $errorFrame['args']);
            self::assertInstanceOf(\SensitiveParameterValue::class, $errorFrame['args'][0]);
            self::assertInstanceOf(\SensitiveParameterValue::class, $errorFrame['args'][1]);
            self::assertInstanceOf(\SensitiveParameterValue::class, $errorFrame['args'][2]);
            self::assertNotInstanceOf(\SensitiveParameterValue::class, $errorFrame['args'][3]);
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    /**
     * @return array<int, array{class-string, string, string[]}>
     */
    private static function inventory(): array
    {
        return [
            [GuzzleClient::class, '__construct', ['config']],
            [GuzzleClient::class, 'getCommand', ['args']],
            [GuzzleClient::class, 'setConfig', ['value']],
            [GuzzleClient::class, 'assertConfigOptionTypes', ['config']],
            [GuzzleClient::class, 'assertConfigOptionType', ['value']],
            [GuzzleClient::class, 'invalidConfigOptionType', ['value']],
            [GuzzleClient::class, 'processConfig', ['config']],
            [Parameter::class, 'filter', ['value']],
            [SchemaValidator::class, 'validate', ['value']],
            [SchemaValidator::class, 'recursiveProcess', ['value']],
            [Serializer::class, '__invoke', ['command']],
            [Serializer::class, 'prepareRequest', ['command', 'request']],
            [Serializer::class, 'createRequest', ['command']],
            [Serializer::class, 'createCommandWithUri', ['command']],
            [AbstractLocation::class, 'prepareValue', ['value']],
            [AbstractLocation::class, 'resolveRecursively', ['value']],
            [BodyLocation::class, 'visit', ['command', 'request']],
            [FormParamLocation::class, 'visit', ['command', 'request']],
            [FormParamLocation::class, 'after', ['command', 'request']],
            [HeaderLocation::class, 'visit', ['command', 'request']],
            [HeaderLocation::class, 'after', ['command', 'request']],
            [HeaderLocation::class, 'prepareHeaderValue', ['value']],
            [JsonLocation::class, 'visit', ['command', 'request']],
            [JsonLocation::class, 'after', ['command', 'request']],
            [MultiPartLocation::class, 'visit', ['command', 'request']],
            [MultiPartLocation::class, 'after', ['command', 'request']],
            [QueryLocation::class, 'visit', ['command', 'request']],
            [QueryLocation::class, 'after', ['command', 'request']],
            [XmlLocation::class, 'visit', ['command', 'request']],
            [XmlLocation::class, 'after', ['command', 'request']],
            [Rfc3986Serializer::class, 'aggregate', ['queryParams']],
            [NonFiniteFloats::class, 'assertAllFinite', ['values']],
            [Deserializer::class, '__invoke', ['response', 'request', 'command']],
            [Deserializer::class, 'handleErrorResponses', ['response', 'request', 'command']],
        ];
    }

    private static function countSourceAttributes(string $directory): int
    {
        $count = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            self::assertNotFalse($contents);
            $count += substr_count($contents, '#[\\SensitiveParameter]');
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    private static function findFrame(\Throwable $exception, string $class, string $function): array
    {
        foreach ($exception->getTrace() as $frame) {
            if (($frame['class'] ?? null) === $class && ($frame['function'] ?? null) === $function) {
                return $frame;
            }
        }

        self::fail('Unable to find '.$class.'::'.$function.' in the exception trace.');
    }
}
