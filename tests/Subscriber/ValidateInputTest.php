<?php

namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\Subscriber\ValidateInput;
use GuzzleHttp\Command\Event\PrepareEvent;

/**
 * @covers GuzzleHttp\Command\Guzzle\Subscriber\ValidateInput
 */
class ValidateInputTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \GuzzleHttp\Command\Exception\CommandException
     * @expectedExceptionMessage Validation errors: [bar] is a required string
     */
    public function testValidates()
    {
        $description = new Description([
            'operations' => [
                'foo' => [
                    'uri' => 'http://httpbin.org',
                    'httpMethod' => 'GET',
                    'responseModel' => 'j',
                    'parameters' => [
                        'bar' => [
                            'type'     => 'string',
                            'required' => true
                        ]
                    ]
                ]
            ]
        ]);

        $client = new GuzzleClient(new Client(), $description);
        $val = new ValidateInput();
        $event = new PrepareEvent(new CommandTransaction(
            $client,
            $client->getCommand('foo')
        ));
        $val->onPrepare($event);
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage is not a GuzzleHttp\Command\Guzzle\GuzzleCommandInterface
     */
    public function testEnsuresCorrectCommandType()
    {
        $val = new ValidateInput();
        $client = $this->getMockBuilder('GuzzleHttp\Command\ServiceClientInterface')
            ->getMockForAbstractClass();
        $command = $this->getMockBuilder('GuzzleHttp\Command\CommandInterface')
            ->getMockForAbstractClass();
        $val->onPrepare(new PrepareEvent(new CommandTransaction(
            $client,
            $command
        )));
    }

    public function testSuccessfulValidationDoesNotThrow()
    {
        $description = new Description([
            'operations' => [
                'foo' => [
                    'uri' => 'http://httpbin.org',
                    'httpMethod' => 'GET',
                    'responseModel' => 'j',
                    'parameters' => []
                ]
            ]
        ]);

        $client = new GuzzleClient(new Client(), $description);
        $val = new ValidateInput();
        $event = new PrepareEvent(new CommandTransaction(
            $client,
            $client->getCommand('foo')
        ));
        $val->onPrepare($event);
    }

    /**
     * @expectedException \GuzzleHttp\Command\Exception\CommandException
     * @expectedExceptionMessage Validation errors: [bar] must be of type string
     */
    public function testValidatesAdditionalParameters()
    {
        $description = new Description([
            'operations' => [
                'foo' => [
                    'uri' => 'http://httpbin.org',
                    'httpMethod' => 'GET',
                    'responseModel' => 'j',
                    'additionalParameters' => [
                        'type'     => 'string'
                    ]
                ]
            ]
        ]);

        $client = new GuzzleClient(new Client(), $description);
        $val = new ValidateInput();
        $event = new PrepareEvent(new CommandTransaction(
            $client,
            $client->getCommand('foo', ['bar' => new \stdClass()])
        ));
        $val->onPrepare($event);
    }
}
