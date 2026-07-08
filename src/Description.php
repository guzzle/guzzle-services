<?php

declare(strict_types=1);

namespace GuzzleHttp\Command\Guzzle;

use GuzzleHttp\Psr7\Uri;

/**
 * Represents a Guzzle service description
 */
class Description implements DescriptionInterface
{
    /** @var array Array of {@see OperationInterface} objects */
    private array $operations = [];

    /** @var array Array of API models */
    private array $models = [];

    /** @var string|null Name of the API */
    private ?string $name = null;

    /** @var string|null API version */
    private ?string $apiVersion = null;

    /** @var string|null Summary of the API */
    private ?string $description = null;

    /** @var array Any extra API data */
    private array $extraData = [];

    /** @var Uri baseUri/basePath */
    private Uri $baseUri;

    private SchemaFormatter $formatter;

    /**
     * The models and operations maps use PHP array keys. Numeric-string names
     * are normalized to integer keys before the constructor receives them.
     *
     * @param array{
     *     name?: string,
     *     models?: array<array-key, array<array-key, mixed>>,
     *     apiVersion?: string,
     *     description?: string,
     *     baseUri?: string|\Stringable,
     *     operations?: array<array-key, array<array-key, mixed>>,
     *     ...
     * } $config Service description data.
     * @param array{formatter?: SchemaFormatter} $options Custom options to apply to the description.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $config, array $options = [])
    {
        // Keep a list of default keys used in service descriptions that is
        // later used to determine extra data keys.
        static $defaultKeys = ['name', 'models', 'apiVersion', 'description'];

        // Pull in the default configuration values.
        if (isset($config['name'])) {
            $this->name = $config['name'];
        }
        if (isset($config['models'])) {
            $this->models = $config['models'];
        }
        if (isset($config['apiVersion'])) {
            $this->apiVersion = $config['apiVersion'];
        }
        if (isset($config['description'])) {
            $this->description = $config['description'];
        }

        // Set the baseUri
        if (isset($config['baseUri'])) {
            $baseUri = $config['baseUri'];
            if (!is_string($baseUri) && (!is_object($baseUri) || !method_exists($baseUri, '__toString'))) {
                throw new \InvalidArgumentException(\sprintf('baseUri must be a string or Stringable; got %s.', get_debug_type($baseUri)));
            }
            $this->baseUri = new Uri((string) $baseUri);
        } else {
            $this->baseUri = new Uri();
        }

        // Ensure that the models and operations properties are always arrays
        $this->models = (array) $this->models;
        $this->operations = (array) $this->operations;

        // We want to add operations differently than adding the other properties
        $defaultKeys[] = 'operations';

        // Create operations for each operation
        if (isset($config['operations'])) {
            foreach ($config['operations'] as $name => $operation) {
                if (!is_array($operation)) {
                    throw new \InvalidArgumentException(\sprintf('Operation "%s" must be an array; got %s.', (string) $name, get_debug_type($operation)));
                }
                $this->operations[$name] = $operation;
            }
        }

        // Get all of the additional properties of the service description and
        // store them in a data array
        foreach (array_diff(array_keys($config), $defaultKeys) as $key) {
            $this->extraData[$key] = $config[$key];
        }

        // Configure the schema formatter
        if (isset($options['formatter'])) {
            $this->formatter = $options['formatter'];
        } else {
            static $defaultFormatter;
            if (!$defaultFormatter) {
                $defaultFormatter = new SchemaFormatter();
            }
            $this->formatter = $defaultFormatter;
        }
    }

    /**
     * Get the basePath/baseUri of the description
     */
    public function getBaseUri(): Uri
    {
        return $this->baseUri;
    }

    /**
     * Get the API operations of the service
     *
     * @return Operation[] Returns an array of {@see Operation} objects
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    /**
     * Check if the service has an operation by name
     *
     * @param string $name Name of the operation to check
     */
    public function hasOperation(string $name): bool
    {
        return isset($this->operations[$name]);
    }

    /**
     * Get an API operation by name
     *
     * @param string $name Name of the command
     *
     * @throws \InvalidArgumentException if the operation is not found
     */
    public function getOperation(string $name): Operation
    {
        if (!$this->hasOperation($name)) {
            throw new \InvalidArgumentException("No operation found named $name");
        }

        // Lazily create operations as they are retrieved
        if (!$this->operations[$name] instanceof Operation) {
            $this->operations[$name]['name'] = $name;
            $this->operations[$name] = new Operation($this->operations[$name], $this);
        }

        return $this->operations[$name];
    }

    /**
     * Get a shared definition structure.
     *
     * @param string $id ID/name of the model to retrieve
     *
     * @throws \InvalidArgumentException if the model is not found
     */
    public function getModel(string $id): Parameter
    {
        if (!$this->hasModel($id)) {
            throw new \InvalidArgumentException("No model found named $id");
        }

        // Lazily create models as they are retrieved
        if (!$this->models[$id] instanceof Parameter) {
            $this->models[$id] = new Parameter(
                $this->models[$id],
                ['description' => $this]
            );
        }

        return $this->models[$id];
    }

    /**
     * Get all models of the service description.
     */
    public function getModels(): array
    {
        $models = [];
        foreach ($this->models as $name => $model) {
            $models[$name] = $this->getModel($name);
        }

        return $models;
    }

    /**
     * Check if the service description has a model by name.
     *
     * @param string $id Name/ID of the model to check
     */
    public function hasModel(string $id): bool
    {
        return isset($this->models[$id]);
    }

    /**
     * Get the API version of the service
     */
    public function getApiVersion(): ?string
    {
        return $this->apiVersion;
    }

    /**
     * Get the name of the API
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Get a summary of the purpose of the API
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Format a parameter using named formats.
     *
     * @param string $format Format to convert it to
     * @param mixed  $input  Input string
     *
     * @return mixed
     */
    public function format(string $format, $input)
    {
        return $this->formatter->format($format, $input);
    }

    /**
     * Get arbitrary data from the service description that is not part of the
     * Guzzle service description specification.
     *
     * @param string $key Data key to retrieve or null to retrieve all extra
     *
     * @return mixed|null
     */
    public function getData(?string $key = null)
    {
        if ($key === null) {
            return $this->extraData;
        } elseif (isset($this->extraData[$key])) {
            return $this->extraData[$key];
        }

        return null;
    }
}
