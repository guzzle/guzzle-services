<?php

namespace GuzzleHttp\Command\Guzzle;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Collection;
use GuzzleHttp\Command\CommandToRequestIterator;
use GuzzleHttp\Event\HasEmitterTrait;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Event\EventWrapper;
use GuzzleHttp\Command\Guzzle\Subscriber\PrepareRequest;
use GuzzleHttp\Command\Guzzle\Subscriber\ProcessResponse;
use GuzzleHttp\Command\Guzzle\Subscriber\ValidateInput;

/**
 * Default Guzzle web service client implementation.
 */
class GuzzleClient implements GuzzleClientInterface
{
    use HasEmitterTrait;

    /** @var ClientInterface HTTP client used to send requests */
    private $client;

    /** @var Description Guzzle service description */
    private $description;

    /** @var Collection Service client configuration data */
    private $config;

    /** @var callable Factory used for creating commands */
    private $commandFactory;

    /**
     * @param ClientInterface   $client      Client used to send HTTP requests
     * @param Description $description Guzzle service description
     * @param array             $config      Configuration options
     *     - defaults: Associative array of default command parameters to add
     *       to each command created by the client.
     *     - validate: Specify if command input is validated (defaults to true).
     *       Changing this setting after the client has been created will have
     *       no effect.
     *     - process: Specify if HTTP responses are parsed (defaults to true).
     *       Changing this setting after the client has been created will have
     *       no effect.
     *     - request_locations: Associative array of location types mapping to
     *       RequestLocationInterface objects.
     *     - response_locations: Associative array of location types mapping to
     *       ResponseLocationInterface objects.
     */
    public function __construct(
        ClientInterface $client,
        Description $description,
        array $config = []
    ) {
        $this->client = $client;
        $this->description = $description;
        if (!isset($config['defaults'])) {
            $config['defaults'] = [];
        }
        $this->config = new Collection($config);
        $this->processConfig();
    }

    public function __call($name, array $arguments)
    {
        return $this->execute($this->getCommand($name, $arguments));
    }

    public function getCommand($name, array $args = [])
    {
        $factory = $this->commandFactory;
        // Merge in default command options
        $args += $this->config['defaults'];

        if (!($command = $factory($name, $args, $this))) {
            throw new \InvalidArgumentException("No operation found named $name");
        }

        return $command;
    }

    public function execute(CommandInterface $command)
    {
        try {
            $event = EventWrapper::prepareCommand($command, $this);
            // Listeners can intercept the event and inject a result. If that
            // happened, then we must not emit further events and just
            // return the result.
            if (null !== ($result = $event->getResult())) {
                return $result;
            }
            $request = $event->getRequest();
            // Send the request and get the response that is used in the
            // complete event.
            $response = $this->client->send($request);
            // Emit the process event for the command and return the result
            return EventWrapper::processCommand($command, $this, $request, $response);
        } catch (CommandException $e) {
            // Let command exceptions pass through untouched
            throw $e;
        } catch (\Exception $e) {
            // Wrap any other exception in a CommandException so that exceptions
            // thrown from the client are consistent and predictable.
            $msg = 'Error executing command: ' . $e->getMessage();
            throw new CommandException($msg, $this, $command, null, null, $e);
        }
    }

    public function executeAll($commands, array $options = [])
    {
        $requestOptions = [];
        // Move all of the options over that affect the request transfer
        if (isset($options['parallel'])) {
            $requestOptions['parallel'] = $options['parallel'];
        }

        // Create an iterator that yields requests from commands and send all
        $this->client->sendAll(
            new CommandToRequestIterator($commands, $this, $options),
            $requestOptions
        );
    }

    public function getHttpClient()
    {
        return $this->client;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getConfig($keyOrPath = null)
    {
        return $keyOrPath === null
            ? $this->config->toArray()
            : $this->config->getPath($keyOrPath);
    }

    public function setConfig($keyOrPath, $value)
    {
        $this->config->setPath($keyOrPath, $value);
    }

    /**
     * Creates a callable function used to create command objects from a
     * service description.
     *
     * @param Description $description Service description
     *
     * @return callable Returns a command factory
     */
    public static function defaultCommandFactory(Description $description)
    {
        return function (
            $name,
            array $args = [],
            GuzzleClientInterface $client
        ) use ($description) {

            $operation = null;

            if ($description->hasOperation($name)) {
                $operation = $description->getOperation($name);
            } else {
                $name = ucfirst($name);
                if ($description->hasOperation($name)) {
                    $operation = $description->getOperation($name);
                }
            }

            if (!$operation) {
                return null;
            }

            return new Command($operation, $args, clone $client->getEmitter());
        };
    }

    /**
     * Prepares the client based on the configuration settings of the client.
     */
    protected function processConfig()
    {
        // Use the passed in command factory or a custom factory if provided
        $this->commandFactory = isset($this->config['command_factory'])
            ? $this->config['command_factory']
            : self::defaultCommandFactory($this->description);

        // Add event listeners based on the configuration option
        $emitter = $this->getEmitter();

        if (!isset($this->config['validate']) ||
            $this->config['validate'] === true
        ) {
            $emitter->attach(new ValidateInput());
        }

        $emitter->attach(new PrepareRequest(
            $this->config['request_locations'] ?: []
        ));

        if (!isset($this->config['process']) ||
            $this->config['process'] === true
        ) {
            $emitter->attach(new ProcessResponse(
                $this->config['response_locations'] ?: []
            ));
        }
    }
}
