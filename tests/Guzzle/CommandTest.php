<?php

namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Command\Guzzle\Command;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\Operation;

/**
 * @covers \GuzzleHttp\Command\Guzzle\Command
 */
class CommandTest extends \PHPUnit_Framework_TestCase
{
    public function testHasData()
    {
        $description = new Description([]);
        $operation = new Operation(['name' => 'foo'], $description);
        $c = new Command($operation, ['baz' => 'bar']);
        $this->assertSame('bar', $c['baz']);
        $this->assertTrue($c->hasParam('baz'));
        $this->assertFalse($c->hasParam('boo'));
        $this->assertEquals('foo', $c->getName());
        $this->assertSame($operation, $c->getOperation());
    }
}
