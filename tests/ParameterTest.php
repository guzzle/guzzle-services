<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\Parameter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Command\Guzzle\Parameter
 */
class ParameterTest extends TestCase
{
    protected array $data = [
        'name' => 'foo',
        'type' => 'bar',
        'required' => true,
        'default' => '123',
        'description' => '456',
        'minLength' => 2,
        'maxLength' => 5,
        'location' => 'body',
        'static' => true,
        'filters' => ['trim', 'json_encode'],
    ];

    public function testCreatesParamFromArray(): void
    {
        $p = new Parameter($this->data);
        $this->assertEquals('foo', $p->getName());
        $this->assertEquals('bar', $p->getType());
        $this->assertTrue($p->isRequired());
        $this->assertEquals('123', $p->getDefault());
        $this->assertEquals('456', $p->getDescription());
        $this->assertEquals(2, $p->getMinLength());
        $this->assertEquals(5, $p->getMaxLength());
        $this->assertEquals('body', $p->getLocation());
        $this->assertTrue($p->isStatic());
        $this->assertEquals(['trim', 'json_encode'], $p->getFilters());
        $p->setName('abc');
        $this->assertEquals('abc', $p->getName());
    }

    public function testValidatesDescription(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Parameter($this->data, ['description' => 'foo']);
    }

    public function testCanConvertToArray(): void
    {
        $p = new Parameter($this->data);
        $this->assertEquals($this->data, $p->toArray());
    }

    public function testUsesStatic(): void
    {
        $d = $this->data;
        $d['default'] = 'booboo';
        $d['static'] = true;
        $p = new Parameter($d);
        $this->assertEquals('booboo', $p->getValue('bar'));
    }

    public function testUsesDefault(): void
    {
        $d = $this->data;
        $d['default'] = 'foo';
        $d['static'] = false;
        $p = new Parameter($d);
        $this->assertEquals('foo', $p->getValue(null));
    }

    public function testReturnsYourValue(): void
    {
        $d = $this->data;
        $d['static'] = false;
        $p = new Parameter($d);
        $this->assertEquals('foo', $p->getValue('foo'));
    }

    public function testZeroValueDoesNotCauseDefaultToBeReturned(): void
    {
        $d = $this->data;
        $d['default'] = '1';
        $d['static'] = false;
        $p = new Parameter($d);
        $this->assertEquals('0', $p->getValue('0'));
    }

    public function testFiltersValues(): void
    {
        $d = $this->data;
        $d['static'] = false;
        $d['filters'] = ['strtoupper'];
        $p = new Parameter($d);
        $this->assertEquals('FOO', $p->filter('foo'));
    }

    public function testRequiresServiceDescriptionForFormatting(): void
    {
        $this->expectExceptionMessage('No service description');
        $this->expectException(\RuntimeException::class);
        $d = $this->data;
        $d['format'] = 'foo';
        $p = new Parameter($d);
        $p->filter('bar');
    }

    public function testConvertsBooleans(): void
    {
        $p = new Parameter(['type' => 'boolean']);
        $this->assertEquals(true, $p->filter('true'));
        $this->assertEquals(false, $p->filter('false'));
    }

    public function testUsesArrayByDefaultForFilters(): void
    {
        $d = $this->data;
        $d['filters'] = [];
        $p = new Parameter($d);
        $this->assertEquals([], $p->getFilters());
    }

    /**
     * @dataProvider invalidParameterDataProvider
     */
    public function testRejectsInvalidParameterData($key, $value, $message): void
    {
        $this->expectExceptionMessage($message);
        $this->expectException(\InvalidArgumentException::class);

        new Parameter([$key => $value]);
    }

    public function invalidParameterDataProvider(): array
    {
        return [
            ['name', [], 'name must be a string or null'],
            ['required', [], 'required must be a boolean'],
            ['static', new \stdClass(), 'static must be a boolean'],
            ['minimum', 'abc', 'minimum must be an integer or null'],
            ['maxItems', 1.5, 'maxItems must be an integer or null'],
            ['minimum', true, 'minimum must be an integer or null'],
            ['filters', true, 'filters must be an array'],
            ['filters', [true], 'Filters must be strings or complex filter arrays'],
            ['filters', [['method' => 'strtolower', 'args' => 'bad']], 'An [args] array must be specified for each complex filter'],
            ['properties', 'foo', 'properties must be an array'],
            ['properties', ['foo' => true], 'properties must contain only arrays or Parameter instances'],
            ['data', 'foo', 'data must be an array'],
            ['enum', 'foo', 'enum must be an array or null'],
            ['additionalProperties', 'foo', 'additionalProperties must be a boolean, array, Parameter, or null'],
            ['items', 'foo', 'items must be an array, Parameter, or null'],
            ['type', true, 'type must be a string, array, or null'],
            ['type', [true], 'type arrays must contain only strings'],
        ];
    }

    public function testInitializesOptionalTypedProperties(): void
    {
        $p = new Parameter();

        $this->assertNull($p->getName());
        $this->assertNull($p->getDescription());
        $this->assertNull($p->getEnum());
        $this->assertNull($p->getPattern());
        $this->assertNull($p->getMinimum());
        $this->assertNull($p->getMaximum());
        $this->assertNull($p->getMinLength());
        $this->assertNull($p->getMaxLength());
        $this->assertNull($p->getMinItems());
        $this->assertNull($p->getMaxItems());
        $this->assertNull($p->getLocation());
        $this->assertNull($p->getSentAs());
        $this->assertNull($p->getFormat());
        $this->assertFalse($p->isRequired());
        $this->assertFalse($p->isStatic());
        $this->assertSame([], $p->getFilters());
    }

    public function testAllowsSimpleLocationValue(): void
    {
        $p = new Parameter(['name' => 'myname', 'location' => 'foo', 'sentAs' => 'Hello']);
        $this->assertEquals('foo', $p->getLocation());
        $this->assertEquals('Hello', $p->getSentAs());
    }

    public function testParsesTypeValues(): void
    {
        $p = new Parameter(['type' => 'foo']);
        $this->assertEquals('foo', $p->getType());
    }

    public function testValidatesComplexFilters(): void
    {
        $this->expectExceptionMessage('A [method] value must be specified for each complex filter');
        $this->expectException(\InvalidArgumentException::class);
        $p = new Parameter(['filters' => [['args' => 'foo']]]);
    }

    public function testAllowsComplexFilters(): void
    {
        $that = $this;
        $param = new Parameter([
            'filters' => [
                [
                    'method' => function (string $a, string $b, string $c, Parameter $d) use ($that, &$param): string {
                        $that->assertEquals('test', $a);
                        $that->assertEquals('my_value!', $b);
                        $that->assertEquals('bar', $c);
                        $that->assertSame($param, $d);

                        return 'abc'.$b;
                    },
                    'args' => ['test', '@value', 'bar', '@api'],
                ],
            ],
        ]);

        $this->assertEquals('abcmy_value!', $param->filter('my_value!'));
    }

    public function testAddsAdditionalProperties(): void
    {
        $p = new Parameter([
            'type' => 'object',
            'additionalProperties' => ['type' => 'string'],
        ]);
        $this->assertInstanceOf('GuzzleHttp\Command\Guzzle\Parameter', $p->getAdditionalProperties());
        $this->assertNull($p->getAdditionalProperties()->getAdditionalProperties());
        $p = new Parameter(['type' => 'object']);
        $this->assertTrue($p->getAdditionalProperties());
    }

    public function testAddsItems(): void
    {
        $p = new Parameter([
            'type' => 'array',
            'items' => ['type' => 'string'],
        ]);
        $this->assertInstanceOf('GuzzleHttp\Command\Guzzle\Parameter', $p->getItems());
        $out = $p->toArray();
        $this->assertEquals('array', $out['type']);
        $this->assertIsArray($out['items']);
    }

    public function testCanRetrieveKnownPropertiesUsingDataMethod(): void
    {
        $p = new Parameter(['data' => ['name' => 'test'], 'extra' => 'hi!']);
        $this->assertEquals('test', $p->getData('name'));
        $this->assertEquals(['name' => 'test'], $p->getData());
        $this->assertNull($p->getData('fjnweefe'));
        $this->assertEquals('hi!', $p->getData('extra'));
    }

    public function testHasPattern(): void
    {
        $p = new Parameter(['pattern' => '/[0-9]+/']);
        $this->assertEquals('/[0-9]+/', $p->getPattern());
    }

    public function testHasEnum(): void
    {
        $p = new Parameter(['enum' => ['foo', 'bar']]);
        $this->assertEquals(['foo', 'bar'], $p->getEnum());
    }

    public function testSerializesItems(): void
    {
        $p = new Parameter([
            'type' => 'object',
            'additionalProperties' => ['type' => 'string'],
        ]);
        $this->assertEquals([
            'type' => 'object',
            'additionalProperties' => ['type' => 'string'],
        ], $p->toArray());
    }

    public function testResolvesRefKeysRecursively(): void
    {
        $description = new Description([
            'models' => [
                'JarJar' => ['type' => 'string', 'default' => 'Mesa address tha senate!'],
                'Anakin' => ['type' => 'array', 'items' => ['$ref' => 'JarJar']],
            ],
        ]);
        $p = new Parameter(['$ref' => 'Anakin', 'description' => 'added'], ['description' => $description]);
        $this->assertEquals([
            'description' => 'added',
            '$ref' => 'Anakin',
        ], $p->toArray());
    }

    public function testResolvesExtendsRecursively(): void
    {
        $jarJar = ['type' => 'string', 'default' => 'Mesa address tha senate!', 'description' => 'a'];
        $anakin = ['type' => 'array', 'items' => ['extends' => 'JarJar', 'description' => 'b']];
        $description = new Description([
            'models' => ['JarJar' => $jarJar, 'Anakin' => $anakin],
        ]);
        // Description attribute will be updated, and format added
        $p = new Parameter(['extends' => 'Anakin', 'format' => 'date'], ['description' => $description]);
        $this->assertEquals([
            'format' => 'date',
            'extends' => 'Anakin',
        ], $p->toArray());
    }

    public function testResolvesNestedExtendsUsingResolvedParentData(): void
    {
        $description = new Description([
            'models' => [
                'Grandparent' => [
                    'type' => 'string',
                    'location' => 'query',
                    'default' => 'grandparent',
                ],
                'Parent' => [
                    'extends' => 'Grandparent',
                    'required' => true,
                    'default' => 'parent',
                ],
                'Child' => [
                    'extends' => 'Parent',
                    'sentAs' => 'child_name',
                ],
            ],
        ]);

        $p = new Parameter(['extends' => 'Child', 'description' => 'actual'], ['description' => $description]);

        $this->assertEquals('string', $p->getType());
        $this->assertEquals('query', $p->getLocation());
        $this->assertTrue($p->isRequired());
        $this->assertEquals('parent', $p->getDefault());
        $this->assertEquals('child_name', $p->getSentAs());
        $this->assertEquals([
            'extends' => 'Child',
            'description' => 'actual',
        ], $p->toArray());
    }

    public function testResolvesRefToModelThatExtendsAnotherModel(): void
    {
        $description = new Description([
            'models' => [
                'Base' => [
                    'type' => 'string',
                    'location' => 'query',
                ],
                'Derived' => [
                    'extends' => 'Base',
                    'default' => 'value',
                    'name' => 'model_name',
                ],
            ],
        ]);

        $p = new Parameter(['$ref' => 'Derived', 'name' => 'input_name'], ['description' => $description]);

        $this->assertEquals('input_name', $p->getName());
        $this->assertEquals('string', $p->getType());
        $this->assertEquals('query', $p->getLocation());
        $this->assertEquals('value', $p->getDefault());
        $this->assertEquals([
            '$ref' => 'Derived',
            'name' => 'input_name',
        ], $p->toArray());
    }

    public function testHasKeyMethod(): void
    {
        $p = new Parameter(['name' => 'foo', 'sentAs' => 'bar']);
        $this->assertEquals('bar', $p->getWireName());
    }

    public function testIncludesNameInToArrayWhenItemsAttributeHasName(): void
    {
        $p = new Parameter([
            'type' => 'array',
            'name' => 'Abc',
            'items' => [
                'name' => 'Foo',
                'type' => 'object',
            ],
        ]);
        $result = $p->toArray();
        $this->assertEquals([
            'type' => 'array',
            'name' => 'Abc',
            'items' => [
                'name' => 'Foo',
                'type' => 'object',
            ],
        ], $result);
    }

    public static function dateTimeProvider(): array
    {
        $d = 'October 13, 2012 16:15:46 UTC';

        return [
            [$d, 'date-time', '2012-10-13T16:15:46Z'],
            [$d, 'date', '2012-10-13'],
            [$d, 'timestamp', strtotime($d)],
            [new \DateTime($d), 'timestamp', strtotime($d)],
        ];
    }

    /**
     * @dataProvider dateTimeProvider
     *
     * @param mixed $d
     * @param mixed $result
     */
    public function testAppliesFormat($d, string $format, $result): void
    {
        $p = new Parameter(['format' => $format], ['description' => new Description([])]);
        $this->assertEquals($format, $p->getFormat());
        $this->assertEquals($result, $p->filter($d));
    }

    public function testHasMinAndMax(): void
    {
        $p = new Parameter([
            'minimum' => 2,
            'maximum' => 3,
            'minItems' => 4,
            'maxItems' => 5,
        ]);
        $this->assertEquals(2, $p->getMinimum());
        $this->assertEquals(3, $p->getMaximum());
        $this->assertEquals(4, $p->getMinItems());
        $this->assertEquals(5, $p->getMaxItems());
    }

    public function testHasProperties(): void
    {
        $data = [
            'type' => 'object',
            'properties' => [
                'foo' => ['type' => 'string'],
                'bar' => ['type' => 'string'],
            ],
        ];
        $p = new Parameter($data);
        $this->assertInstanceOf('GuzzleHttp\\Command\\Guzzle\\Parameter', $p->getProperty('foo'));
        $this->assertSame($p->getProperty('foo'), $p->getProperty('foo'));
        $this->assertNull($p->getProperty('wefwe'));

        $properties = $p->getProperties();
        $this->assertIsArray($properties);
        foreach ($properties as $prop) {
            $this->assertInstanceOf('GuzzleHttp\\Command\\Guzzle\\Parameter', $prop);
        }

        $this->assertEquals($data, $p->toArray());
    }

    public function testHasReturnsFalseForWrongOrEmptyValues(): void
    {
        $emptyParam = new Parameter();
        $this->assertFalse($emptyParam->has(''));
        $this->assertFalse($emptyParam->has('description'));
        $this->assertFalse($emptyParam->has('noExisting'));
    }

    public function testHasReturnsTrueForCorrectValues(): void
    {
        $p = new Parameter([
            'minimum' => 2,
            'maximum' => 3,
            'minItems' => 4,
            'maxItems' => 5,
        ]);

        $this->assertTrue($p->has('minimum'));
        $this->assertTrue($p->has('maximum'));
        $this->assertTrue($p->has('minItems'));
        $this->assertTrue($p->has('maxItems'));
    }
}
