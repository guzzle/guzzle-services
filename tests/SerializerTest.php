<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\Serializer;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;

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
        $this->assertEquals('http://test.com/api/bar/foo', $request->getUri());
    }

    public function testAllowsAdditionalParametersWithoutLocation()
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
}
