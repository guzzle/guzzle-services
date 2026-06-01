<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\RequestLocation\RequestLocationInterface;
use GuzzleHttp\Command\Guzzle\Serializer;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * @covers \GuzzleHttp\Command\Guzzle\Serializer
 */
class SerializerTest extends TestCase
{
    public function testAllowsUriTemplates(): void
    {
        $description = new Description([
            'baseUri' => 'http://test.com',
            'operations' => [
                'test' => [
                    'httpMethod' => 'GET',
                    'uri' => '/api/{key}/foo',
                    'parameters' => [
                        'key' => [
                            'required' => true,
                            'type' => 'string',
                            'location' => 'uri',
                        ],
                    ],
                ],
            ],
        ]);

        $command = new Command('test', ['key' => 'bar']);
        $serializer = new Serializer($description);
        /** @var Request */
        $request = $serializer($command);
        $this->assertSame('GET', $request->getMethod());
        $this->assertEquals('http://test.com/api/bar/foo', $request->getUri());
    }

    public function testPreservesConfiguredHttpMethodCasing(): void
    {
        $description = new Description([
            'baseUri' => 'http://test.com',
            'operations' => [
                'test' => [
                    'httpMethod' => 'get',
                    'uri' => '/api',
                ],
            ],
        ]);

        $command = new Command('test');
        $serializer = new Serializer($description);
        $request = $serializer($command);

        $this->assertSame('get', $request->getMethod());
    }

    public function testCreatesRequestWithUppercaseMethodWithoutUriTemplate(): void
    {
        $description = new Description([
            'baseUri' => 'http://test.com',
            'operations' => [
                'test' => [
                    'httpMethod' => 'POST',
                ],
            ],
        ]);

        $request = (new Serializer($description))(new Command('test'));

        $this->assertSame('POST', $request->getMethod());
        $this->assertEquals('http://test.com', $request->getUri());
    }

    public function testAllowsAdditionalParametersWithoutLocation(): void
    {
        $description = new Description([
            'baseUri' => 'http://test.com',
            'operations' => [
                'test' => [
                    'httpMethod' => 'GET',
                    'uri' => '/api',
                    'additionalParameters' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ]);

        $command = new Command('test', ['extra' => 'value']);
        $serializer = new Serializer($description);
        /** @var Request $request */
        $request = $serializer($command);
        $this->assertEquals('http://test.com/api', $request->getUri());
    }

    public function testDoesNotLeakJsonLocationStateAfterFailedSerialization(): void
    {
        $request = $this->serializeAfterFailure('json');

        $this->assertSame('{"public":"ok"}', (string) $request->getBody());
    }

    public function testDoesNotLeakFormParamLocationStateAfterFailedSerialization(): void
    {
        $request = $this->serializeAfterFailure('formParam');

        $this->assertSame('public=ok', (string) $request->getBody());
    }

    public function testDoesNotLeakMultipartLocationStateAfterFailedSerialization(): void
    {
        $body = (string) $this->serializeAfterFailure('multipart')->getBody();

        $this->assertStringContainsString('name="public"', $body);
        $this->assertStringContainsString('ok', $body);
        $this->assertStringNotContainsString('name="secret"', $body);
        $this->assertStringNotContainsString('TOPSECRET', $body);
    }

    public function testDoesNotLeakXmlLocationStateAfterFailedSerialization(): void
    {
        $body = (string) $this->serializeAfterFailure('xml')->getBody();

        $this->assertStringContainsString('<public>ok</public>', $body);
        $this->assertStringNotContainsString('secret', $body);
        $this->assertStringNotContainsString('TOPSECRET', $body);
    }

    public function testSerializerInstancesDoNotShareDefaultRequestLocationState(): void
    {
        $description = $this->createFailedSerializationDescription('json');
        $first = new Serializer($description, ['fail' => $this->createFailingRequestLocation()]);
        $second = new Serializer($description);

        $this->triggerFailedSerialization($first);
        $request = $second(new Command('Next', ['public' => 'ok']));

        $this->assertSame('{"public":"ok"}', (string) $request->getBody());
    }

    public function testCustomRequestLocationIsPreservedAfterFailedSerialization(): void
    {
        $customLocation = new class implements RequestLocationInterface {
            public function visit(
                CommandInterface $command,
                RequestInterface $request,
                Parameter $param
            ): RequestInterface {
                return $request->withHeader('X-Custom-Request-Location', $param->getWireName());
            }

            public function after(
                CommandInterface $command,
                RequestInterface $request,
                Operation $operation
            ): RequestInterface {
                return $request;
            }
        };

        $serializer = new Serializer(
            $this->createFailedSerializationDescription('json'),
            ['fail' => $this->createFailingRequestLocation(), 'json' => $customLocation]
        );

        $this->triggerFailedSerialization($serializer);
        $request = $serializer(new Command('Next', ['public' => 'ok']));

        $this->assertSame('public', $request->getHeaderLine('X-Custom-Request-Location'));
        $this->assertSame('', (string) $request->getBody());
    }

    private function serializeAfterFailure(string $statefulLocation): RequestInterface
    {
        $serializer = new Serializer(
            $this->createFailedSerializationDescription($statefulLocation),
            ['fail' => $this->createFailingRequestLocation()]
        );

        $this->triggerFailedSerialization($serializer);

        return $serializer(new Command('Next', ['public' => 'ok']));
    }

    private function triggerFailedSerialization(Serializer $serializer): void
    {
        try {
            $serializer(new Command('Fail', [
                'secret' => 'TOPSECRET',
                'break' => true,
            ]));
            $this->fail('Expected request serialization to fail.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Synthetic request location failure.', $e->getMessage());
        }
    }

    private function createFailedSerializationDescription(string $statefulLocation): Description
    {
        return new Description([
            'baseUri' => 'https://example.test',
            'operations' => [
                'Fail' => [
                    'httpMethod' => 'POST',
                    'uri' => '/fail',
                    'parameters' => [
                        'secret' => ['type' => 'string', 'location' => $statefulLocation],
                        'break' => ['type' => 'boolean', 'location' => 'fail'],
                    ],
                ],
                'Next' => [
                    'httpMethod' => 'POST',
                    'uri' => '/next',
                    'parameters' => [
                        'public' => ['type' => 'string', 'location' => $statefulLocation],
                    ],
                ],
            ],
        ]);
    }

    private function createFailingRequestLocation(): RequestLocationInterface
    {
        return new class implements RequestLocationInterface {
            public function visit(
                CommandInterface $command,
                RequestInterface $request,
                Parameter $param
            ): RequestInterface {
                throw new \InvalidArgumentException('Synthetic request location failure.');
            }

            public function after(
                CommandInterface $command,
                RequestInterface $request,
                Operation $operation
            ): RequestInterface {
                return $request;
            }
        };
    }
}
