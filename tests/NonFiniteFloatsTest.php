<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Command\Guzzle\NonFiniteFloats;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Command\Guzzle\NonFiniteFloats
 */
class NonFiniteFloatsTest extends TestCase
{
    /**
     * @dataProvider nonFiniteFloatProvider
     */
    public function testAssertFiniteRejectsNonFiniteFloats(float $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Non-finite floats are not supported for a query location value.');

        NonFiniteFloats::assertFinite($value, 'a query location value');
    }

    /**
     * @dataProvider nonFiniteFloatProvider
     */
    public function testAssertAllFiniteRejectsNestedNonFiniteFloats(float $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Non-finite floats are not supported for a query location value.');

        NonFiniteFloats::assertAllFinite(['a' => ['b' => $value]], 'a query location value');
    }

    public function testAssertFiniteAcceptsFiniteAndNonFloatValues(): void
    {
        NonFiniteFloats::assertFinite(1.5, 'a query location value');
        NonFiniteFloats::assertFinite('value', 'a query location value');
        NonFiniteFloats::assertFinite(1, 'a query location value');
        NonFiniteFloats::assertAllFinite(['a' => 1.5, 'b' => ['c' => 'value']], 'a query location value');

        $this->expectNotToPerformAssertions();
    }

    public static function nonFiniteFloatProvider(): array
    {
        return [
            'NAN' => [\NAN],
            'INF' => [\INF],
            '-INF' => [-\INF],
        ];
    }
}
