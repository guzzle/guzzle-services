<?php

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
    protected $operations;

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

    public function testConstructor()
    {
        $service = new Description(['operations' => $this->operations]);
        $this->assertCount(1, $service->getOperations());
        $this->assertFalse($service->hasOperation('foobar'));
        $this->assertTrue($service->hasOperation('test_command'));
    }

    public function testContainsModels()
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

    public function testCanUseLegacyResponseClass()
    {
        $deprecations = [];

        \set_error_handler(static function (int $severity, string $message) use (&$deprecations): bool {
            if ($severity !== \E_USER_DEPRECATED) {
                return false;
            }

            $deprecations[] = $message;

            return true;
        });

        try {
            $d = new Description([
                'operations' => [
                    'foo' => ['responseClass' => 'Tag'],
                ],
                'models' => ['Tag' => ['type' => 'object']],
            ]);
        } finally {
            \restore_error_handler();
        }

        $op = $d->getOperation('foo');
        $this->assertSame('Tag', $op->getResponseModel());
        $this->assertSame([
            'Since guzzlehttp/guzzle-services 1.6: The "responseClass" operation option is deprecated; use "responseModel" instead.',
        ], $deprecations);
    }

    public function testRetrievingMissingModelThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $d = new Description([]);
        $d->getModel('foo');
    }

    public function testHasAttributes()
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

    public function testPersistsCustomAttributes()
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

    public function testThrowsExceptionForMissingOperation()
    {
        $this->expectException(InvalidArgumentException::class);
        $s = new Description([]);
        $this->assertNull($s->getOperation('foo'));
    }

    public function testValidatesOperationTypes()
    {
        $this->expectException(InvalidArgumentException::class);
        new Description([
            'operations' => ['foo' => new \stdClass()],
        ]);
    }

    public function testCanUseLegacyBaseUrl()
    {
        $deprecations = [];

        \set_error_handler(static function (int $severity, string $message) use (&$deprecations): bool {
            if ($severity !== \E_USER_DEPRECATED) {
                return false;
            }

            $deprecations[] = $message;

            return true;
        });

        try {
            $description = new Description(['baseUrl' => 'http://foo.com']);
        } finally {
            \restore_error_handler();
        }

        $this->assertEquals('http://foo.com', $description->getBaseUri());
        $this->assertSame([
            'Since guzzlehttp/guzzle-services 1.6: The "baseUrl" service description option is deprecated; use "baseUri" instead.',
        ], $deprecations);
    }

    public function testHasbaseUri()
    {
        $description = new Description(['baseUri' => 'http://foo.com']);
        $this->assertEquals('http://foo.com', $description->getBaseUri());
    }

    public function testModelsHaveNames()
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

    public function testHasOperations()
    {
        $desc = ['operations' => ['foo' => ['parameters' => ['foo' => [
            'name' => 'foo',
        ]]]]];
        $s = new Description($desc);
        $this->assertInstanceOf(Operation::class, $s->getOperation('foo'));
        $this->assertSame($s->getOperation('foo'), $s->getOperation('foo'));
    }

    public function testHasFormatter()
    {
        $s = new Description([]);
        $this->assertNotEmpty($s->format('date', 'now'));
    }

    public function testCanUseCustomFormatter()
    {
        $formatter = $this->getMockBuilder(SchemaFormatter::class)
            ->setMethods(['format'])
            ->getMock();
        $formatter->expects($this->once())
            ->method('format');
        $s = new Description([], ['formatter' => $formatter]);
        $s->format('time', 'now');
    }
}
