<?php

declare(strict_types=1);

namespace GuzzleHttp\Command\Guzzle;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Guzzle\Handler\ValidatedDescriptionHandler;
use GuzzleHttp\Command\Guzzle\ResponseLocation\ResponseLocationInterface;
use GuzzleHttp\Command\ResultInterface;
use GuzzleHttp\Command\ServiceClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\DiagnosticValue;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Default Guzzle web service client implementation.
 */
class GuzzleClient extends ServiceClient
{
    private array $config;

    /** @var DescriptionInterface Guzzle service description */
    private DescriptionInterface $description;

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
     *   When false, the raw response is returned in the command result.
     *   Individual operations can override this setting with their own process
     *   option.
     *   Changing this setting after the client has been created will have no
     *   effect.
     * - response_locations: Associative array of location types mapping to
     *   ResponseLocationInterface objects.
     *
     * The response_locations map uses PHP array keys. Numeric-string keys are
     * normalized to integer keys before the constructor receives them.
     *
     * @param ClientInterface                                                                         $client                      HTTP client to use.
     * @param DescriptionInterface                                                                    $description                 Guzzle service description.
     * @param (callable(CommandInterface): RequestInterface)|null                                     $commandToRequestTransformer Command-to-request transformer.
     * @param (callable(ResponseInterface, RequestInterface, CommandInterface): ResultInterface)|null $responseToResultTransformer Response-to-result transformer.
     * @param HandlerStack<callable(CommandInterface): PromiseInterface<ResultInterface, mixed>>|null $commandHandlerStack         Command handler stack.
     * @param array{
     *     defaults?: array<array-key, mixed>,
     *     validate?: bool,
     *     process?: bool,
     *     response_locations?: array<array-key, ResponseLocationInterface>,
     *     ...
     * } $config Configuration options.
     */
    public function __construct(
        ClientInterface $client,
        DescriptionInterface $description,
        ?callable $commandToRequestTransformer = null,
        ?callable $responseToResultTransformer = null,
        ?HandlerStack $commandHandlerStack = null,
        array $config = []
    ) {
        self::assertConfigOptionTypes($config);

        $this->config = $config;
        $this->description = $description;
        $serializer = $this->getSerializer($commandToRequestTransformer);
        $deserializer = $this->getDeserializer($responseToResultTransformer);

        parent::__construct($client, $serializer, $deserializer, $commandHandlerStack);
        $this->processConfig($config);
    }

    /**
     * Returns the command if valid; otherwise an Exception
     *
     * @throws \InvalidArgumentException
     */
    public function getCommand(string $name, array $args = []): CommandInterface
    {
        if (!$this->description->hasOperation($name)) {
            $name = Utils::asciiUcFirst($name);
            if (!$this->description->hasOperation($name)) {
                throw new \InvalidArgumentException(\sprintf('No operation found named %s', DiagnosticValue::escape($name)));
            }
        }

        // Merge in default command options
        $args += $this->getConfig('defaults');

        return parent::getCommand($name, $args);
    }

    /**
     * Return the description
     */
    public function getDescription(): DescriptionInterface
    {
        return $this->description;
    }

    /**
     * Returns the passed Serializer when set, a new instance otherwise
     *
     * @param (callable(CommandInterface): RequestInterface)|null $commandToRequestTransformer
     *
     * @return callable(CommandInterface): RequestInterface
     */
    private function getSerializer(?callable $commandToRequestTransformer): callable
    {
        return $commandToRequestTransformer !== null
            ? $commandToRequestTransformer
            : new Serializer($this->description);
    }

    /**
     * Returns the passed Deserializer when set, a new instance otherwise
     *
     * @param (callable(ResponseInterface, RequestInterface, CommandInterface): ResultInterface)|null $responseToResultTransformer
     *
     * @return callable(ResponseInterface, RequestInterface, CommandInterface): ResultInterface
     */
    private function getDeserializer(?callable $responseToResultTransformer): callable
    {
        $process = (!isset($this->config['process']) || $this->config['process'] === true);

        return $responseToResultTransformer !== null
            ? $responseToResultTransformer
            : new Deserializer($this->description, $process, $this->config['response_locations'] ?? []);
    }

    /**
     * Get the config of the client
     *
     * @param array|string $option
     *
     * @return mixed
     */
    public function getConfig($option = null)
    {
        return $option === null
            ? $this->config
            : (isset($this->config[$option]) ? $this->config[$option] : []);
    }

    public function setConfig($option, $value): void
    {
        if (is_int($option) || is_string($option)) {
            self::assertConfigOptionType((string) $option, $value);
        }

        $this->config[$option] = $value;
    }

    /**
     * @return void
     */
    private static function assertConfigOptionTypes(array $config)
    {
        foreach ($config as $option => $value) {
            self::assertConfigOptionType((string) $option, $value);
        }
    }

    /**
     * @param mixed $value
     *
     * @return void
     */
    private static function assertConfigOptionType(string $option, $value)
    {
        if ($option === 'defaults' && !is_array($value)) {
            self::invalidConfigOptionType($option, 'array', $value);

            return;
        }

        if (($option === 'validate' || $option === 'process') && !is_bool($value)) {
            self::invalidConfigOptionType($option, 'bool', $value);

            return;
        }

        if ($option !== 'response_locations') {
            return;
        }

        if (!is_array($value)) {
            self::invalidConfigOptionType($option, 'array', $value);

            return;
        }

        foreach ($value as $name => $location) {
            if (!$location instanceof ResponseLocationInterface) {
                self::invalidConfigOptionType(
                    $option.'.'.(string) $name,
                    ResponseLocationInterface::class,
                    $location
                );
            }
        }
    }

    /**
     * @param mixed $value
     *
     * @return void
     */
    private static function invalidConfigOptionType(string $option, string $expected, $value)
    {
        throw new \InvalidArgumentException(\sprintf(
            'Passing %s to GuzzleClient config option "%s" is invalid; expected %s.',
            get_debug_type($value),
            DiagnosticValue::escape($option),
            $expected
        ));
    }

    /**
     * Prepares the client based on the configuration settings of the client.
     *
     * @param array $config Constructor config as an array
     */
    protected function processConfig(array $config): void
    {
        // set defaults as an array if not provided
        if (!isset($config['defaults'])) {
            $config['defaults'] = [];
        }

        // Add the handlers based on the configuration option
        $stack = $this->getHandlerStack();

        if (!isset($config['validate']) || $config['validate'] === true) {
            $stack->push(new ValidatedDescriptionHandler($this->description), 'validate_description');
        }
    }
}
