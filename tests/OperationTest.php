<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\Parameter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Command\Guzzle\Operation
 */
class OperationTest extends TestCase
{
    public static function strtoupper(string $string): string
    {
        return strtoupper($string);
    }

    public function testOperationIsDataObject(): void
    {
        $c = new Operation([
            'name' => 'test',
            'summary' => 'doc',
            'notes' => 'notes',
            'documentationUrl' => 'http://www.example.com',
            'httpMethod' => 'POST',
            'uri' => '/api/v1',
            'responseModel' => 'abc',
            'deprecated' => true,
            'parameters' => [
                'key' => [
                    'required' => true,
                    'type' => 'string',
                    'maxLength' => 10,
                    'name' => 'key',
                ],
                'key_2' => [
                    'required' => true,
                    'type' => 'integer',
                    'default' => 10,
                    'name' => 'key_2',
                ],
            ],
        ]);

        $this->assertEquals('test', $c->getName());
        $this->assertEquals('doc', $c->getSummary());
        $this->assertEquals('http://www.example.com', $c->getDocumentationUrl());
        $this->assertEquals('POST', $c->getHttpMethod());
        $this->assertEquals('/api/v1', $c->getUri());
        $this->assertEquals('abc', $c->getResponseModel());
        $this->assertTrue($c->getDeprecated());

        $params = array_map(function (Parameter $c): array {
            return $c->toArray();
        }, $c->getParams());

        $this->assertEquals([
            'key' => [
                'required' => true,
                'type' => 'string',
                'maxLength' => 10,
                'name' => 'key',
            ],
            'key_2' => [
                'required' => true,
                'type' => 'integer',
                'default' => 10,
                'name' => 'key_2',
            ],
        ], $params);

        $this->assertEquals([
            'required' => true,
            'type' => 'integer',
            'default' => 10,
            'name' => 'key_2',
        ], $c->getParam('key_2')->toArray());

        $this->assertNull($c->getParam('afefwef'));
        $this->assertArrayNotHasKey('parent', $c->getParam('key_2')->toArray());
    }

    public function testDeterminesIfHasParam(): void
    {
        $command = $this->getTestCommand();
        $this->assertTrue($command->hasParam('data'));
        $this->assertFalse($command->hasParam('baz'));
    }

    protected function getTestCommand(): Operation
    {
        return new Operation([
            'parameters' => [
                'data' => ['type' => 'string'],
            ],
        ]);
    }

    public function testAddsNameToParametersIfNeeded(): void
    {
        $command = new Operation(['parameters' => ['foo' => []]]);
        $this->assertEquals('foo', $command->getParam('foo')->getName());
    }

    public function testContainsApiErrorInformation(): void
    {
        $command = $this->getOperation();
        $this->assertCount(1, $command->getErrorResponses());
    }

    public function testHasNotes(): void
    {
        $o = new Operation(['notes' => 'foo']);
        $this->assertEquals('foo', $o->getNotes());
    }

    public function testHasData(): void
    {
        $o = new Operation(['data' => ['foo' => 'baz', 'bar' => 123]]);
        $this->assertEquals('baz', $o->getData('foo'));
        $this->assertEquals(123, $o->getData('bar'));
        $this->assertNull($o->getData('wfefwe'));
        $this->assertEquals(['foo' => 'baz', 'bar' => 123], $o->getData());
    }

    public function testDefaultsHttpMethodToGet(): void
    {
        $o = new Operation();

        $this->assertEquals('GET', $o->getHttpMethod());
        $this->assertEquals('GET', $o->toArray()['httpMethod']);
    }

    public function testCanProvideAlternateHttpMethod(): void
    {
        $o = new Operation(['httpMethod' => 'POST']);

        $this->assertEquals('POST', $o->getHttpMethod());
        $this->assertEquals('POST', $o->toArray()['httpMethod']);
    }

    public function testDefaultsProcessToNull(): void
    {
        $o = new Operation();

        $this->assertNull($o->getProcess());
        $this->assertNull($o->toArray()['process']);
    }

    public function testCanDisableResponseProcessing(): void
    {
        $o = new Operation(['process' => false]);

        $this->assertFalse($o->getProcess());
        $this->assertFalse($o->toArray()['process']);
    }

    public function testCanEnableResponseProcessing(): void
    {
        $o = new Operation(['process' => true]);

        $this->assertTrue($o->getProcess());
        $this->assertTrue($o->toArray()['process']);
    }

    public function testEnsuresProcessIsBoolOrNull(): void
    {
        $this->expectExceptionMessage('process must be a boolean or null');
        $this->expectException(\InvalidArgumentException::class);

        new Operation(['process' => 'false']);
    }

    public function testEnsuresHttpMethodIsNotEmptyString(): void
    {
        $this->expectExceptionMessage('httpMethod must be a non-empty string');
        $this->expectException(\InvalidArgumentException::class);

        new Operation(['httpMethod' => '']);
    }

    public function testEnsuresHttpMethodIsString(): void
    {
        $this->expectExceptionMessage('httpMethod must be a non-empty string');
        $this->expectException(\InvalidArgumentException::class);

        new Operation(['httpMethod' => false]);
    }

    public function testEnsuresParametersAreArrays(): void
    {
        $this->expectExceptionMessage('Passing bool as operation parameter "test.foo" is invalid; expected array.');
        $this->expectException(\InvalidArgumentException::class);
        new Operation(['name' => 'test', 'parameters' => ['foo' => true]]);
    }

    public function testHasDescription(): void
    {
        $s = new Description([]);
        $o = new Operation([], $s);
        $this->assertSame($s, $o->getServiceDescription());
    }

    public function testHasAdditionalParameters(): void
    {
        $o = new Operation([
            'additionalParameters' => [
                'type' => 'string', 'name' => 'binks',
            ],
            'parameters' => [
                'foo' => ['type' => 'integer'],
            ],
        ]);
        $this->assertEquals('string', $o->getAdditionalParameters()->getType());
    }

    public function testCanProvideAdditionalParametersAsParameter(): void
    {
        $parameter = new Parameter(['type' => 'string']);
        $operation = new Operation(['additionalParameters' => $parameter]);
        $this->assertSame($parameter, $operation->getAdditionalParameters());
    }

    public function testEnsuresAdditionalParametersAreArrayOrParameter(): void
    {
        $this->expectExceptionMessage('additionalParameters must be an array or Parameter');
        $this->expectException(\InvalidArgumentException::class);
        new Operation(['additionalParameters' => true]);
    }

    protected function getOperation(): Operation
    {
        return new Operation([
            'name' => 'OperationTest',
            'class' => get_class($this),
            'parameters' => [
                'test' => ['type' => 'object'],
                'bool_1' => ['default' => true, 'type' => 'boolean'],
                'bool_2' => ['default' => false],
                'float' => ['type' => 'numeric'],
                'int' => ['type' => 'integer'],
                'date' => ['type' => 'string'],
                'timestamp' => ['type' => 'string'],
                'string' => ['type' => 'string'],
                'username' => ['type' => 'string', 'required' => true, 'filters' => ['strtolower']],
                'test_function' => ['type' => 'string', 'filters' => [__CLASS__.'::strtoupper']],
            ],
            'errorResponses' => [
                [
                    'code' => 503,
                    'reason' => 'InsufficientCapacity',
                    'class' => 'Guzzle\\Exception\\RuntimeException',
                ],
            ],
        ]);
    }

    public function testCanExtendFromOtherOperations(): void
    {
        $d = new Description([
            'operations' => [
                'A' => [
                    'process' => false,
                    'parameters' => [
                        'A' => [
                            'type' => 'object',
                            'properties' => ['foo' => ['type' => 'string']],
                        ],
                        'B' => ['type' => 'string'],
                    ],
                    'summary' => 'foo',
                ],
                'B' => [
                    'extends' => 'A',
                    'httpMethod' => 'POST',
                    'summary' => 'Bar',
                ],
                'C' => [
                    'extends' => 'B',
                    'process' => true,
                    'summary' => 'Bar',
                    'parameters' => [
                        'B' => ['type' => 'number'],
                    ],
                ],
            ],
        ]);

        $a = $d->getOperation('A');
        $this->assertEquals('GET', $a->getHttpMethod());
        $this->assertFalse($a->getProcess());
        $this->assertEquals('foo', $a->getSummary());
        $this->assertTrue($a->hasParam('A'));
        $this->assertEquals('string', $a->getParam('B')->getType());

        $b = $d->getOperation('B');
        $this->assertTrue($a->hasParam('A'));
        $this->assertEquals('POST', $b->getHttpMethod());
        $this->assertFalse($b->getProcess());
        $this->assertEquals('Bar', $b->getSummary());
        $this->assertEquals('string', $a->getParam('B')->getType());

        $c = $d->getOperation('C');
        $this->assertTrue($a->hasParam('A'));
        $this->assertEquals('POST', $c->getHttpMethod());
        $this->assertTrue($c->getProcess());
        $this->assertEquals('Bar', $c->getSummary());
        $this->assertEquals('number', $c->getParam('B')->getType());
    }
}
