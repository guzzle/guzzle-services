<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\SchemaFormatter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Command\Guzzle\Description
 */
class DescriptionTest extends TestCase
{
    protected array $operations;

    public function setup(): void
    {
        $this->operations = [
            'test_command' => [
                'name' => 'test_command',
                'description' => 'documentationForCommand',
                'httpMethod' => 'DELETE',
                'class' => 'FooModel',
                'parameters' => [
                    'bucket' => ['required' => true],
                    'key' => ['required' => true],
                ],
            ],
        ];
    }

    public function testConstructor(): void
    {
        $service = new Description(['operations' => $this->operations]);
        $this->assertCount(1, $service->getOperations());
        $this->assertFalse($service->hasOperation('foobar'));
        $this->assertTrue($service->hasOperation('test_command'));
    }

    public function testContainsModels(): void
    {
        $d = new Description([
            'operations' => ['foo' => []],
            'models' => [
                'Tag' => ['type' => 'object'],
                'Person' => ['type' => 'object'],
            ],
        ]);
        $this->assertTrue($d->hasModel('Tag'));
        $this->assertTrue($d->hasModel('Person'));
        $this->assertFalse($d->hasModel('Foo'));
        $this->assertInstanceOf(Parameter::class, $d->getModel('Tag'));
        $this->assertEquals(['Tag', 'Person'], array_keys($d->getModels()));
    }

    public function testDoesNotUseLegacyResponseClass(): void
    {
        $d = new Description([
            'operations' => [
                'foo' => ['responseClass' => 'Tag'],
            ],
            'models' => ['Tag' => ['type' => 'object']],
        ]);
        $op = $d->getOperation('foo');
        $this->assertNull($op->getResponseModel());
        $this->assertSame('Tag', $op->toArray()['responseClass']);
    }

    public function testRetrievingMissingModelThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $d = new Description([]);
        $d->getModel('foo');
    }

    public function testHasAttributes(): void
    {
        $d = new Description([
            'operations' => [],
            'name' => 'Name',
            'description' => 'Description',
            'apiVersion' => '1.24',
        ]);

        $this->assertEquals('Name', $d->getName());
        $this->assertEquals('Description', $d->getDescription());
        $this->assertEquals('1.24', $d->getApiVersion());
    }

    public function testPersistsCustomAttributes(): void
    {
        $data = [
            'operations' => ['foo' => ['class' => 'foo', 'parameters' => []]],
            'name' => 'Name',
            'description' => 'Test',
            'apiVersion' => '1.24',
            'auth' => 'foo',
            'keyParam' => 'bar',
        ];
        $d = new Description($data);
        $this->assertEquals('foo', $d->getData('auth'));
        $this->assertEquals('bar', $d->getData('keyParam'));
        $this->assertEquals(['auth' => 'foo', 'keyParam' => 'bar'], $d->getData());
        $this->assertNull($d->getData('missing'));
    }

    public function testThrowsExceptionForMissingOperation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $s = new Description([]);
        $this->assertNull($s->getOperation('foo'));
    }

    public function testValidatesOperationTypes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operation "foo" must be an array; got stdClass.');

        new Description([
            'operations' => ['foo' => new \stdClass()],
        ]);
    }

    public function testDoesNotUseLegacyBaseUrl(): void
    {
        $description = new Description(['baseUrl' => 'http://foo.com']);
        $this->assertEquals('', $description->getBaseUri());
        $this->assertSame('http://foo.com', $description->getData('baseUrl'));
    }

    public function testHasbaseUri(): void
    {
        $description = new Description(['baseUri' => 'http://foo.com']);
        $this->assertEquals('http://foo.com', $description->getBaseUri());
    }

    public function testModelsHaveNames(): void
    {
        $desc = [
            'models' => [
                'date' => ['type' => 'string'],
                'user' => [
                    'type' => 'object',
                    'properties' => [
                        'dob' => ['$ref' => 'date'],
                    ],
                ],
            ],
        ];

        $s = new Description($desc);
        $this->assertEquals('string', $s->getModel('date')->getType());
        $this->assertEquals('dob', $s->getModel('user')->getProperty('dob')->getName());
    }

    public function testHasOperations(): void
    {
        $desc = ['operations' => ['foo' => ['parameters' => ['foo' => [
            'name' => 'foo',
        ]]]]];
        $s = new Description($desc);
        $this->assertInstanceOf(Operation::class, $s->getOperation('foo'));
        $this->assertSame($s->getOperation('foo'), $s->getOperation('foo'));
    }

    public function testHasFormatter(): void
    {
        $s = new Description([]);
        $this->assertNotEmpty($s->format('date', 'now'));
    }

    public function testCanUseCustomFormatter(): void
    {
        $formatter = $this->getMockBuilder(SchemaFormatter::class)
            ->onlyMethods(['format'])
            ->getMock();
        $formatter->expects($this->once())
            ->method('format');
        $s = new Description([], ['formatter' => $formatter]);
        $s->format('time', 'now');
    }
}
