<?php

declare(strict_types=1);

namespace GuzzleHttp\Command\Guzzle;

use GuzzleHttp\Command\ToArrayInterface;
use GuzzleHttp\Psr7\DiagnosticValue;

/**
 * Guzzle operation
 *
 * @final
 */
class Operation implements ToArrayInterface
{
    /** @var array Parameters */
    private array $parameters = [];

    /** @var Parameter Additional parameters schema */
    private ?Parameter $additionalParameters = null;

    private DescriptionInterface $description;

    /** @var array Config data */
    private array $config;

    /**
     * Builds an Operation object using an array of configuration data.
     *
     * - name: (string) Name of the command
     * - httpMethod: (string) HTTP method of the operation
     * - uri: (string) URI template that can create a relative or absolute URL
     * - parameters: (array) Associative array of parameters for the command.
     *   Each value must be an array that is used to create {@see Parameter}
     *   objects.
     * - summary: (string) This is a short summary of what the operation does
     * - notes: (string) A longer description of the operation.
     * - documentationUrl: (string) Reference URL providing more information
     *   about the operation.
     * - responseModel: (string) The model name used for processing response.
     * - process: (bool|null) Whether this operation's HTTP response should be
     *   parsed. Null inherits the client setting.
     * - deprecated: (bool) Set to true if this is a deprecated command
     * - errorResponses: (array) Errors that could occur when executing the
     *   command. Array of hashes, each with a 'code' (the HTTP response code),
     *   'phrase' (response reason phrase or description of the error), and
     *   'class' (a custom exception class that would be thrown if the error is
     *   encountered).
     * - data: (array) Any extra data that might be used to help build or
     *   serialize the operation
     * - additionalParameters: (null|array) Parameter schema to use when an
     *   option is passed to the operation that is not in the schema
     *
     * @param array{
     *     name?: string,
     *     extends?: string,
     *     httpMethod?: string,
     *     uri?: string|null,
     *     parameters?: array<array-key, array<array-key, mixed>>,
     *     summary?: string,
     *     notes?: string,
     *     documentationUrl?: string|null,
     *     responseModel?: string|null,
     *     process?: bool|null,
     *     deprecated?: bool,
     *     errorResponses?: array<array-key, array{
     *         code: int|string,
     *         class: string,
     *         phrase?: string,
     *         ...
     *     }>,
     *     data?: array<array-key, mixed>,
     *     additionalParameters?: array<array-key, mixed>|Parameter|null,
     *     ...
     * } $config Array of configuration data.
     * @param DescriptionInterface|null $description Service description used to resolve models if $ref tags are found.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $config = [], ?DescriptionInterface $description = null)
    {
        static $defaults = [
            'name' => '',
            'httpMethod' => 'GET',
            'uri' => '',
            'responseModel' => null,
            'process' => null,
            'notes' => '',
            'summary' => '',
            'documentationUrl' => null,
            'deprecated' => false,
            'data' => [],
            'parameters' => [],
            'additionalParameters' => null,
            'errorResponses' => [],
        ];

        $this->description = $description === null ? new Description([]) : $description;

        if (isset($config['extends'])) {
            $config = $this->resolveExtends($config['extends'], $config);
        }

        if (array_key_exists('httpMethod', $config)
            && (!is_string($config['httpMethod']) || $config['httpMethod'] === '')
        ) {
            throw new \InvalidArgumentException('httpMethod must be a non-empty string');
        }

        if (array_key_exists('process', $config)
            && $config['process'] !== null
            && !is_bool($config['process'])
        ) {
            throw new \InvalidArgumentException('process must be a boolean or null');
        }

        $this->config = $config + $defaults;

        $this->resolveParameters();
    }

    public function toArray(): array
    {
        return $this->config;
    }

    /**
     * Get the service description that the operation belongs to
     */
    public function getServiceDescription(): DescriptionInterface
    {
        return $this->description;
    }

    /**
     * Get the params of the operation
     *
     * @return Parameter[]
     */
    public function getParams(): array
    {
        return $this->parameters;
    }

    /**
     * Get additionalParameters of the operation
     */
    public function getAdditionalParameters(): ?Parameter
    {
        return $this->additionalParameters;
    }

    /**
     * Check if the operation has a specific parameter by name
     *
     * @param string $name Name of the param
     */
    public function hasParam(string $name): bool
    {
        return isset($this->parameters[$name]);
    }

    /**
     * Get a single parameter of the operation
     *
     * @param string $name Parameter to retrieve by name
     */
    public function getParam(string $name): ?Parameter
    {
        return isset($this->parameters[$name])
            ? $this->parameters[$name]
            : null;
    }

    /**
     * Get the HTTP method of the operation
     */
    public function getHttpMethod(): string
    {
        return $this->config['httpMethod'];
    }

    /**
     * Get the name of the operation
     */
    public function getName(): ?string
    {
        return $this->config['name'];
    }

    /**
     * Get a short summary of what the operation does
     */
    public function getSummary(): ?string
    {
        return $this->config['summary'];
    }

    /**
     * Get a longer text field to explain the behavior of the operation
     */
    public function getNotes(): ?string
    {
        return $this->config['notes'];
    }

    /**
     * Get the documentation URL of the operation
     */
    public function getDocumentationUrl(): ?string
    {
        return $this->config['documentationUrl'];
    }

    /**
     * Get the name of the model used for processing the response.
     */
    public function getResponseModel(): ?string
    {
        return $this->config['responseModel'];
    }

    /**
     * Get whether this operation overrides response processing.
     */
    public function getProcess(): ?bool
    {
        return $this->config['process'];
    }

    /**
     * Get whether or not the operation is deprecated
     */
    public function getDeprecated(): bool
    {
        return $this->config['deprecated'];
    }

    /**
     * Get the URI that will be merged into the generated request
     */
    public function getUri(): ?string
    {
        return $this->config['uri'];
    }

    /**
     * Get the errors that could be encountered when executing the operation
     */
    public function getErrorResponses(): array
    {
        return $this->config['errorResponses'];
    }

    /**
     * Get extra data from the operation
     *
     * @param string $name Name of the data point to retrieve or null to
     *                     retrieve all of the extra data.
     *
     * @return mixed|null
     */
    public function getData(?string $name = null)
    {
        if ($name === null) {
            return $this->config['data'];
        } elseif (isset($this->config['data'][$name])) {
            return $this->config['data'][$name];
        }

        return null;
    }

    private function resolveExtends(string $name, array $config): array
    {
        if (!$this->description->hasOperation($name)) {
            throw new \InvalidArgumentException(\sprintf('No operation named %s', DiagnosticValue::escape($name)));
        }

        // Merge parameters together one level deep
        $base = $this->description->getOperation($name)->toArray();
        $result = $config + $base;

        if (isset($base['parameters']) && isset($config['parameters'])) {
            $result['parameters'] = $config['parameters'] + $base['parameters'];
        }

        return $result;
    }

    /**
     * Process the description and extract the parameter config
     */
    private function resolveParameters(): void
    {
        // Parameters need special handling when adding
        foreach ($this->config['parameters'] as $name => $param) {
            if (!is_array($param)) {
                throw new \InvalidArgumentException(\sprintf(
                    'Passing %s as operation parameter "%s.%s" is invalid; expected array.',
                    get_debug_type($param),
                    DiagnosticValue::escape((string) $this->config['name']),
                    DiagnosticValue::escape((string) $name)
                ));
            }
            $param['name'] = $name;
            $this->parameters[$name] = new Parameter(
                $param,
                ['description' => $this->description]
            );
        }

        if ($this->config['additionalParameters']) {
            if (is_array($this->config['additionalParameters'])) {
                $this->additionalParameters = new Parameter(
                    $this->config['additionalParameters'],
                    ['description' => $this->description]
                );
            } elseif ($this->config['additionalParameters'] instanceof Parameter) {
                $this->additionalParameters = $this->config['additionalParameters'];
            } else {
                throw new \InvalidArgumentException('additionalParameters must be an array or Parameter');
            }
        }
    }
}
