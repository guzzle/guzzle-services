<?php

namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Command\Event\PrepareEvent;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Event\BeforeEvent;

/**
 * @covers \GuzzleHttp\Command\Guzzle\GuzzleClient
 */
class GuzzleClientTest extends \PHPUnit_Framework_TestCase
{
    public function testHasConfig()
    {
        $client = new Client();
        $description = new Description([]);
        $guzzle = new GuzzleClient($client, $description, [
            'foo' => 'bar',
            'baz' => ['bam' => 'boo']
        ]);
        $this->assertSame($client, $guzzle->getHttpClient());
        $this->assertSame($description, $guzzle->getDescription());
        $this->assertEquals('bar', $guzzle->getConfig('foo'));
        $this->assertEquals('boo', $guzzle->getConfig('baz/bam'));
        $this->assertEquals([], $guzzle->getConfig('defaults'));
        $guzzle->setConfig('abc/123', 'listen');
        $this->assertEquals('listen', $guzzle->getConfig('abc/123'));

        $this->assertCount(2, $guzzle->getEmitter()->listeners('prepare'));
        $this->assertCount(1, $guzzle->getEmitter()->listeners('process'));
    }

    public function testAddsSubscribersWhenTrue()
    {
        $client = new Client();
        $description = new Description([]);
        $guzzle = new GuzzleClient($client, $description, [
            'validate' => true,
            'process' => true
        ]);
        $this->assertCount(2, $guzzle->getEmitter()->listeners('prepare'));
        $this->assertCount(1, $guzzle->getEmitter()->listeners('process'));
    }

    public function testDisablesSubscribersWhenFalse()
    {
        $client = new Client();
        $description = new Description([]);
        $guzzle = new GuzzleClient($client, $description, [
            'validate' => false,
            'process' => false
        ]);
        $this->assertCount(1, $guzzle->getEmitter()->listeners('prepare'));
        $this->assertCount(0, $guzzle->getEmitter()->listeners('process'));
    }

    public function testCanUseCustomConfigFactory()
    {
        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\Guzzle\\Command')
            ->disableOriginalConstructor()
            ->getMock();
        $client = new Client();
        $description = new Description([]);
        $guzzle = new GuzzleClient($client, $description, [
            'command_factory' => function () use ($mock) {
                $this->assertCount(3, func_get_args());
                return $mock;
            }
        ]);
        $this->assertSame($mock, $guzzle->getCommand('foo'));
    }

    public function testMagicMethodExecutesCommands()
    {
        $mock = $this->getMockBuilder('GuzzleHttp\\Command\\Guzzle\\Command')
            ->disableOriginalConstructor()
            ->getMock();
        $client = new Client();
        $description = new Description([]);
        $guzzle = $this->getMockBuilder('GuzzleHttp\\Command\\Guzzle\\GuzzleClient')
            ->setConstructorArgs([
                $client, $description, [
                    'command_factory' => function ($name) use ($mock) {
                        $this->assertEquals('foo', $name);
                        $this->assertCount(3, func_get_args());
                        return $mock;
                    }
                ]
            ])
            ->setMethods(['execute'])
            ->getMock();
        $guzzle->expects($this->once())
            ->method('execute')
            ->will($this->returnValue('foo'));

        $this->assertEquals('foo', $guzzle->foo([]));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage No operation found named foo
     */
    public function testThrowsWhenFactoryReturnsNull()
    {
        $client = new Client();
        $description = new Description([]);
        $guzzle = new GuzzleClient($client, $description);
        $guzzle->getCommand('foo');
    }

    public function testDefaultFactoryChecksWithUppercaseToo()
    {
        $description = new Description([
            'operations' => ['Foo' => [], 'bar' => []]
        ]);
        $c = new GuzzleClient(new Client(), $description);
        $f = GuzzleClient::defaultCommandFactory($description);
        $command1 = $f('foo', [], $c);
        $this->assertInstanceOf('GuzzleHttp\\Command\\Guzzle\\Command', $command1);
        $this->assertEquals('Foo', $command1->getName());
        $command2 = $f('Foo', [], $c);
        $this->assertInstanceOf('GuzzleHttp\\Command\\Guzzle\\Command', $command2);
        $this->assertEquals('Foo', $command2->getName());
    }

    /**
     * @expectedException \GuzzleHttp\Command\Exception\CommandException
     */
    public function testPassesCommandExceptionsThrough()
    {
        $description = new Description(['operations' => ['Foo' => []]]);
        $guzzle = new GuzzleClient(new Client(), $description);
        $command = $guzzle->getCommand('foo');
        $command->getEmitter()->on('prepare', function(PrepareEvent $event) {
            throw new CommandException(
                'foo',
                $event->getClient(),
                $event->getCommand(),
                $event->getRequest()
            );
        }, 1);
        $guzzle->execute($command);
    }

    /**
     * @expectedException \GuzzleHttp\Command\Exception\CommandException
     * @expectedExceptionMessage Error executing command: msg
     */
    public function testWrapsExceptionsInCommandExceptions()
    {
        $description = new Description(['operations' => ['Foo' => []]]);
        $guzzle = new GuzzleClient(new Client(), $description);
        $command = $guzzle->getCommand('foo');
        $command->getEmitter()->on('prepare', function(PrepareEvent $event) {
            throw new \Exception('msg');
        }, 1);
        $guzzle->execute($command);
    }

    public function testReturnsInterceptedResult()
    {
        $description = new Description(['operations' => ['Foo' => []]]);
        $guzzle = new GuzzleClient(new Client(), $description);
        $command = $guzzle->getCommand('foo');
        $command->getEmitter()->on('prepare', function(PrepareEvent $event) {
            $event->setResult('test');
        }, 1);
        $this->assertEquals('test', $guzzle->execute($command));
    }

    public function testReturnsProcessedResponse()
    {
        $client = new Client();
        $client->getEmitter()->on('before', function (BeforeEvent $event) {
            $event->intercept(new Response(201));
        });
        $description = new Description([
            'operations' => [
                'Foo' => ['responseModel' => 'Bar']
            ],
            'models' => [
                'Bar' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['location' => 'statusCode']
                    ]
                ]
            ]
        ]);
        $guzzle = new GuzzleClient($client, $description);
        $command = $guzzle->getCommand('foo');
        $command->getEmitter()->on('prepare', function(PrepareEvent $event) {
            $event->setRequest($event->getClient()->getHttpClient()->createRequest('GET', 'http://httbin.org'));
        }, 1);
        $result = $guzzle->execute($command);
        $this->assertInstanceOf('GuzzleHttp\\Command\\Model', $result);
        $this->assertEquals(201, $result['code']);
    }

    public function testExecutesCommandsInParallel()
    {
        $client = $this->getMockBuilder('GuzzleHttp\\Client')
            ->setMethods(['sendAll'])
            ->getMock();

        $description = new Description(['operations' => ['Foo' => []]]);
        $guzzle = new GuzzleClient($client, $description);
        $command = $guzzle->getCommand('foo');
        $request = $client->createRequest('GET', 'http://httbin.org');
        $command->getEmitter()->on('prepare', function (PrepareEvent $e) use ($request) {
            $e->setRequest($request);
        }, 1);

        $client->expects($this->once())
            ->method('sendAll')
            ->will($this->returnCallback(function ($requests, $options) use ($request) {
                $this->assertEquals(10, $options['parallel']);
                $this->assertTrue($requests->valid());
                $this->assertSame($request, $requests->current());
            }));

        $guzzle->executeAll([$command], ['parallel' => 10]);
    }
}
