<?php
namespace GuzzleHttp\Tests\Command\Guzzle\Subscriber;

use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Command\CommandTransaction;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\Subscriber\HandleErrorResponse;
use RuntimeException;

/**
 * @covers GuzzleHttp\Command\Guzzle\Subscriber\HandleErrorResponse
 */
class HandleErrorResponseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \GuzzleHttp\Command\ServiceClientInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $serviceClient;

    /**
     * @var \GuzzleHttp\Command\CommandInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $command;

    public function setUp()
    {
        $this->serviceClient = $this->getMock('GuzzleHttp\Command\Guzzle\GuzzleClient', [], [], '', false);
        $this->command       = $this->getMock('GuzzleHttp\Command\CommandInterface');
    }

    public function testDoNothingIfNoException()
    {
        $commandTransaction = new CommandTransaction($this->serviceClient, $this->command);
        $processEvent       = new ProcessEvent($commandTransaction);

        $subscriber = new HandleErrorResponse();
        $this->assertNull($subscriber->handleError($processEvent));
    }

    /**
     * @expectedException \GuzzleHttp\Tests\Command\Guzzle\Asset\Exception\CustomCommandException
     */
    public function testCreateExceptionWithCode()
    {
        $response = $this->getMock('Psr\Http\Message\ResponseInterface');
        $response->expects($this->any())->method('getStatusCode')->will($this->returnValue(404));

        $commandTransaction            = new CommandTransaction($this->serviceClient, $this->command);
        $commandTransaction->response  = $response;
        $commandTransaction->exception = new RuntimeException();

        $processEvent = new ProcessEvent($commandTransaction);

        $this->prepareEvent($processEvent, 'foo', [
            ['code' => 404, 'class' => 'GuzzleHttp\Tests\Command\Guzzle\Asset\Exception\CustomCommandException']
        ]);

        $subscriber = new HandleErrorResponse();
        $subscriber->handleError($processEvent);
    }

    public function testNotCreateExceptionIfDoesNotMatchCode()
    {
        $response = $this->getMock('Psr\Http\Message\ResponseInterface');
        $response->expects($this->any())->method('getStatusCode')->will($this->returnValue(401));

        $commandTransaction            = new CommandTransaction($this->serviceClient, $this->command);
        $commandTransaction->response  = $response;
        $commandTransaction->exception = new RuntimeException();

        $processEvent = new ProcessEvent($commandTransaction);

        $this->prepareEvent($processEvent, 'foo', [
            ['code' => 404, 'class' => 'GuzzleHttp\Tests\Command\Guzzle\Asset\Exception\CustomCommandException']
        ]);

        $subscriber = new HandleErrorResponse();
        $this->assertNull($subscriber->handleError($processEvent));
    }

    /**
     * @expectedException \GuzzleHttp\Tests\Command\Guzzle\Asset\Exception\CustomCommandException
     */
    public function testCreateExceptionWithExactMatchOfReasonPhrase()
    {
        $response = $this->getMock('Psr\Http\Message\ResponseInterface');
        $response->expects($this->any())->method('getStatusCode')->will($this->returnValue(404));
        $response->expects($this->any())->method('getReasonPhrase')->will($this->returnValue('Bar'));

        $commandTransaction            = new CommandTransaction($this->serviceClient, $this->command);
        $commandTransaction->response  = $response;
        $commandTransaction->exception = new RuntimeException();

        $processEvent = new ProcessEvent($commandTransaction);

        $this->prepareEvent($processEvent, 'foo', [
            ['code' => 404, 'phrase' => 'Bar', 'class' => 'GuzzleHttp\Tests\Command\Guzzle\Asset\Exception\CustomCommandException']
        ]);

        $subscriber = new HandleErrorResponse();
        $subscriber->handleError($processEvent);
    }

    /**
     * @expectedException \GuzzleHttp\Tests\Command\Guzzle\Asset\Exception\OtherCustomCommandException
     */
    public function testFavourMostPreciseMatch()
    {
        $response = $this->getMock('Psr\Http\Message\ResponseInterface');
        $response->expects($this->any())->method('getStatusCode')->will($this->returnValue(404));
        $response->expects($this->any())->method('getReasonPhrase')->will($this->returnValue('Bar'));

        $commandTransaction            = new CommandTransaction($this->serviceClient, $this->command);
        $commandTransaction->response  = $response;
        $commandTransaction->exception = new RuntimeException();

        $processEvent = new ProcessEvent($commandTransaction);

        $this->prepareEvent($processEvent, 'foo', [
            ['code' => 404, 'class' => 'GuzzleHttp\Tests\Command\Guzzle\Asset\Exception\CustomCommandException'],
            ['code' => 404, 'phrase' => 'Bar', 'class' => 'GuzzleHttp\Tests\Command\Guzzle\Asset\Exception\OtherCustomCommandException']
        ]);

        $subscriber = new HandleErrorResponse();
        $subscriber->handleError($processEvent);
    }

    private function prepareEvent(ProcessEvent $event, $commandName, array $errors = [])
    {
        $this->command->expects($this->once())->method('getName')->will($this->returnValue($commandName));

        $description = $this->getMock('GuzzleHttp\Command\Guzzle\DescriptionInterface');
        $operation   = new Operation(['errorResponses' => $errors], $description);

        $description->expects($this->once())->method('getOperation')->with($commandName)->will($this->returnValue($operation));

        $this->serviceClient->expects($this->once())->method('getDescription')->will($this->returnValue($description));
    }
}
