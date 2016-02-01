<?php
namespace GuzzleHttp\Command\Guzzle;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Command\Command;
use GuzzleHttp\Command\Guzzle\Subscriber\ProcessResponse; // @TODO Remove
use GuzzleHttp\Command\Guzzle\Subscriber\ValidateInput; // @TODO Remove
use GuzzleHttp\Command\ServiceClient;

/**
 * Default Guzzle web service client implementation.
 */
class GuzzleClient extends ServiceClient
{
    /** @var Description Guzzle service description */
    private $description;

    /** @var callable Factory used for creating commands */
    private $commandFactory;

    /** @var callable Serializer */
    private $serializer;

    /**
     * The client constructor accepts an associative array of configuration
     * options:
     *
     * - defaults: Associative array of default command parameters to add to
     *   each command created by the client.
     * - validate: Specify if command input is validated (defaults to true).
     *   Changing this setting after the client has been created will have no
     *   effect.
     * - process: Specify if HTTP responses are parsed (defaults to true).
     *   Changing this setting after the client has been created will have no
     *   effect.
     * - response_locations: Associative array of location types mapping to
     *   ResponseLocationInterface objects.
     * - serializer: Optional callable that accepts a CommandTransactions and
     *   returns a serialized request object.
     *
     * @param ClientInterface      $client      HTTP client to use.
     * @param DescriptionInterface $description Guzzle service description
     * @param array                $config      Configuration options
     */
    public function __construct(
        ClientInterface $client,
        DescriptionInterface $description,
        array $config = []
    ) {
        parent::__construct($client); // @todo
        $this->description = $description;
        //$this->processConfig($config);
    }

    public function getCommand($name, array $args = [])
    {
        if (!$this->description->hasOperation($name)) {
            $name = ucfirst($name);
            if (!$this->description->hasOperation($name)) {
                throw new \InvalidArgumentException("No operation found named $name");
            }
        }

        return parent::getCommand($name, $args);
    }

    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Prepares the client based on the configuration settings of the client.
     *
     * @param array $config Constructor config as an array
     */
    protected function processConfig(array $config)
    {
        // set defaults as an array if not provided
        if (!isset($config['defaults'])) {
            $config['defaults'] = [];
        }
        
        // Use the passed in command factory or a custom factory if provided
        $this->commandFactory = isset($config['command_factory'])
            ? $config['command_factory']
            : self::defaultCommandFactory($this->description);

        // Add event listeners based on the configuration option
        $emitter = $this->getEmitter();

        if (!isset($config['validate']) ||
            $config['validate'] === true
        ) {
            $emitter->attach(new ValidateInput($this->description));
        }

        $this->serializer = isset($config['serializer'])
            ? $config['serializer']
            : new Serializer($this->description);

        if (!isset($config['process']) ||
            $config['process'] === true
        ) {
            $emitter->attach(
                new ProcessResponse(
                    $this->description,
                    isset($config['response_locations'])
                        ? $config['response_locations']
                        : []
                )
            );
        }
    }
}
