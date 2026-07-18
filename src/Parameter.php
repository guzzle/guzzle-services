<?php

declare(strict_types=1);

namespace GuzzleHttp\Command\Guzzle;

use GuzzleHttp\Command\ToArrayInterface;

/**
 * API parameter object used with service descriptions
 */
#[\AllowDynamicProperties]
class Parameter implements ToArrayInterface
{
    private array $originalData;

    private array $resolvedData;

    private ?string $name = null;

    private ?string $description = null;

    /** @var string|array */
    private $type;

    private bool $required = false;

    private ?array $enum = null;

    private ?string $pattern = null;

    private ?int $minimum = null;

    private ?int $maximum = null;

    private ?int $minLength = null;

    private ?int $maxLength = null;

    private ?int $minItems = null;

    private ?int $maxItems = null;

    /** @var mixed */
    private $default;

    private bool $static = false;

    /** @var array<array-key, string|array{method: callable, args: array<array-key, mixed>}> */
    private array $filters = [];

    private ?string $location = null;

    private ?string $sentAs = null;

    private array $data = [];

    private array $properties = [];

    /** @var array|bool|Parameter */
    private $additionalProperties;

    /** @var array|Parameter */
    private $items;

    private ?string $format = null;

    private ?array $propertiesCache = null;

    private ?DescriptionInterface $serviceDescription = null;

    /**
     * Create a new Parameter using an associative array of data.
     *
     * The array can contain the following information:
     *
     * - name: (string) Unique name of the parameter
     *
     * - type: (string|array) Type of variable (string, number, integer,
     *   boolean, object, array, numeric, null, any). Types are used for
     *   validation and determining the structure of a parameter. You can use a
     *   union type by providing an array of simple types. If one of the union
     *   types matches the provided value, then the value is valid.
     *
     * - required: (bool) Whether or not the parameter is required
     *
     * - default: (mixed) Default value to use if no value is supplied
     *
     * - static: (bool) Set to true to specify that the parameter value cannot
     *   be changed from the default.
     *
     * - description: (string) Documentation of the parameter
     *
     * - location: (string) The location of a request used to apply a parameter.
     *   Custom locations can be registered with a command, but the defaults
     *   are uri, query, header, body, json, xml, formParam, multipart.
     *
     * - sentAs: (string) Specifies how the data being modeled is sent over the
     *   wire. For example, you may wish to include certain headers in a
     *   response model that have a normalized casing of FooBar, but the actual
     *   header is x-foo-bar. In this case, sentAs would be set to x-foo-bar.
     *
     * - filters: (array) Array of static method names to run a parameter
     *   value through. Each value in the array must be a string containing the
     *   full class path to a static method or an array of complex filter
     *   information. You can specify static methods of classes using the full
     *   namespace class name followed by '::' (e.g. Foo\Bar::baz). Some
     *   filters require arguments in order to properly filter a value. For
     *   complex filters, use a hash containing a 'method' key pointing to a
     *   static method, and an 'args' key containing an array of positional
     *   arguments to pass to the method. Arguments can contain keywords that
     *   are replaced when filtering a value: '@value' is replaced with the
     *   value being validated, '@api' is replaced with the Parameter object.
     *
     * - properties: When the type is an object, you can specify nested parameters
     *
     * - additionalProperties: (array) This attribute defines a schema for all
     *   properties that are not explicitly defined in an object type
     *   definition. If specified, the value MUST be a schema or a boolean. If
     *   false is provided, no additional properties are allowed beyond the
     *   properties defined in the schema. The default value is an empty schema
     *   which allows any value for additional properties.
     *
     * - items: This attribute defines the allowed items in an instance array,
     *   and MUST be a schema or an array of schemas. The default value is an
     *   empty schema which allows any value for items in the instance array.
     *   When this attribute value is a schema and the instance value is an
     *   array, then all the items in the array MUST be valid according to the
     *   schema.
     *
     * - pattern: When the type is a string, you can specify the regex pattern
     *   that a value must match
     *
     * - enum: When the type is a string, you can specify a list of acceptable
     *   values.
     *
     * - minItems: (int) Minimum number of items allowed in an array
     *
     * - maxItems: (int) Maximum number of items allowed in an array
     *
     * - minLength: (int) Minimum length of a string
     *
     * - maxLength: (int) Maximum length of a string
     *
     * - minimum: (int) Minimum value of an integer
     *
     * - maximum: (int) Maximum value of an integer
     *
     * - data: (array) Any additional custom data to use when serializing,
     *   validating, etc
     *
     * - format: (string) Format used to coax a value into the correct format
     *   when serializing or unserializing. You may specify either an array of
     *   filters OR a format, but not both. Supported values: date-time, date,
     *   time, timestamp, date-time-http, and boolean-string.
     *
     * - $ref: (string) String referencing a service description model. The
     *   parameter is replaced by the schema contained in the model.
     *
     * @param array{
     *     name?: string,
     *     type?: string|array<array-key, string>,
     *     required?: bool,
     *     default?: mixed,
     *     static?: bool,
     *     description?: string,
     *     location?: string,
     *     sentAs?: string,
     *     filters?: array<array-key, string|array{method: callable, args: array<array-key, mixed>}>,
     *     properties?: array<array-key, array<array-key, mixed>|Parameter>,
     *     additionalProperties?: bool|array<array-key, mixed>|Parameter|null,
     *     items?: array<array-key, mixed>|Parameter,
     *     pattern?: string,
     *     enum?: array<array-key, mixed>,
     *     minItems?: int,
     *     maxItems?: int,
     *     minLength?: int,
     *     maxLength?: int,
     *     minimum?: int,
     *     maximum?: int,
     *     data?: array<array-key, mixed>,
     *     format?: string,
     *     '$ref'?: string,
     *     extends?: string,
     *     instanceOf?: string,
     *     ...
     * } $data Array of data as seen in service descriptions.
     * @param array{description?: DescriptionInterface} $options Options used when creating the parameter.
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $data = [], array $options = [])
    {
        $this->originalData = $data;

        foreach (['$ref', 'extends', 'name'] as $key) {
            if (array_key_exists($key, $data)) {
                $data[$key] = self::normalizeStringValue($key, $data[$key]);
            }
        }

        if (isset($options['description'])) {
            if (!$options['description'] instanceof DescriptionInterface) {
                throw new \InvalidArgumentException('description must be a Description');
            }
            $this->serviceDescription = $options['description'];
            if (isset($data['$ref'])) {
                if ($model = $this->serviceDescription->getModel($data['$ref'])) {
                    $name = isset($data['name']) ? $data['name'] : null;
                    $data = $model->toResolvedArray() + $data;
                    if ($name) {
                        $data['name'] = $name;
                    }
                }
            } elseif (isset($data['extends'])) {
                // If this parameter extends from another parameter then start
                // with the actual data union in the parent's data (e.g. actual
                // supersedes parent)
                if ($extends = $this->serviceDescription->getModel($data['extends'])) {
                    $data += $extends->toResolvedArray();
                }
            }
        }

        $this->resolvedData = [];

        // Pull configuration data into the parameter
        foreach ($data as $key => $value) {
            $value = self::normalizeParameterDataValue($key, $value);
            if ($key === 'filters') {
                $this->setFilters($value);
                $this->resolvedData[$key] = $value;

                continue;
            }

            $this->{$key} = $value;
            $this->resolvedData[$key] = $value;
        }

        if ($this->type == 'object' && $this->additionalProperties === null) {
            $this->additionalProperties = true;
        }
    }

    /**
     * Convert the object to an array
     */
    public function toArray(): array
    {
        return $this->originalData;
    }

    /**
     * Convert the object to its internally resolved array
     */
    private function toResolvedArray(): array
    {
        return $this->resolvedData;
    }

    /**
     * @param string|int $key
     * @param mixed      $value
     *
     * @return mixed
     */
    private static function normalizeParameterDataValue($key, $value)
    {
        switch ($key) {
            case 'name':
            case 'description':
            case 'location':
            case 'sentAs':
            case 'pattern':
            case 'format':
            case '$ref':
            case 'extends':
                return self::normalizeStringValue((string) $key, $value);
            case 'required':
            case 'static':
                return self::normalizeBooleanValue((string) $key, $value);
            case 'minimum':
            case 'maximum':
            case 'minLength':
            case 'maxLength':
            case 'minItems':
            case 'maxItems':
                return self::normalizeIntegerValue((string) $key, $value);
            case 'filters':
                return self::normalizeFiltersValue($value);
            case 'properties':
                return self::normalizePropertiesValue($value);
            case 'data':
                return self::normalizeArrayValue('data', $value);
            case 'enum':
                return self::normalizeNullableArrayValue('enum', $value);
            case 'additionalProperties':
                return self::normalizeAdditionalPropertiesValue($value);
            case 'items':
                return self::normalizeItemsValue($value);
            case 'type':
                return self::normalizeTypeValue($value);
            default:
                return $value;
        }
    }

    /**
     * @param mixed $value
     */
    private static function normalizeStringValue(string $key, $value): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException(\sprintf('%s must be a string or null; got %s.', $key, get_debug_type($value)));
    }

    /**
     * @param mixed $value
     */
    private static function normalizeBooleanValue(string $key, $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        throw new \InvalidArgumentException(\sprintf('%s must be a boolean; got %s.', $key, get_debug_type($value)));
    }

    /**
     * @param mixed $value
     */
    private static function normalizeIntegerValue(string $key, $value): ?int
    {
        if ($value === null || is_int($value)) {
            return $value;
        }

        throw new \InvalidArgumentException(\sprintf('%s must be an integer or null; got %s.', $key, get_debug_type($value)));
    }

    /**
     * @param mixed $value
     */
    private static function normalizeFiltersValue($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        throw new \InvalidArgumentException(\sprintf('filters must be an array; got %s.', get_debug_type($value)));
    }

    /**
     * @param mixed $value
     */
    private static function normalizePropertiesValue($value): array
    {
        $value = self::normalizeArrayValue('properties', $value);
        foreach ($value as $property) {
            if (!is_array($property) && !$property instanceof self) {
                throw new \InvalidArgumentException(\sprintf('properties must contain only arrays or Parameter instances; got %s.', get_debug_type($property)));
            }
        }

        return $value;
    }

    /**
     * @param mixed $value
     */
    private static function normalizeArrayValue(string $key, $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        throw new \InvalidArgumentException(\sprintf('%s must be an array; got %s.', $key, get_debug_type($value)));
    }

    /**
     * @param mixed $value
     */
    private static function normalizeNullableArrayValue(string $key, $value): ?array
    {
        if ($value === null || is_array($value)) {
            return $value;
        }

        throw new \InvalidArgumentException(\sprintf('%s must be an array or null; got %s.', $key, get_debug_type($value)));
    }

    /**
     * @param mixed $value
     *
     * @return array|bool|Parameter|null
     */
    private static function normalizeAdditionalPropertiesValue($value)
    {
        if ($value === null || is_bool($value) || is_array($value) || $value instanceof self) {
            return $value;
        }

        throw new \InvalidArgumentException(\sprintf('additionalProperties must be a boolean, array, Parameter, or null; got %s.', get_debug_type($value)));
    }

    /**
     * @param mixed $value
     *
     * @return array|Parameter|null
     */
    private static function normalizeItemsValue($value)
    {
        if ($value === null || is_array($value) || $value instanceof self) {
            return $value;
        }

        throw new \InvalidArgumentException(\sprintf('items must be an array, Parameter, or null; got %s.', get_debug_type($value)));
    }

    /**
     * @param mixed $value
     *
     * @return string|array|null
     */
    private static function normalizeTypeValue($value)
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $type) {
                if (!is_string($type)) {
                    throw new \InvalidArgumentException(\sprintf('type arrays must contain only strings; got %s.', get_debug_type($type)));
                }
            }

            return $value;
        }

        throw new \InvalidArgumentException(\sprintf('type must be a string, array, or null; got %s.', get_debug_type($value)));
    }

    /**
     * Get the default or static value of the command based on a value
     *
     * @param string $value Value that is currently set
     *
     * @return mixed Returns the value, a static value if one is present, or a default value
     */
    public function getValue($value)
    {
        if ($this->static || ($this->default !== null && $value === null)) {
            return $this->default;
        }

        return $value;
    }

    /**
     * Run a value through the filters OR format attribute associated with the
     * parameter.
     *
     * @param mixed $value Value to filter
     *
     * @return mixed Returns the filtered value
     *
     * @throws \RuntimeException when trying to format when no service
     *                           description is available.
     */
    public function filter(
        #[\SensitiveParameter]
        $value
    ) {
        // Formats are applied exclusively and supersed filters
        if ($this->format) {
            if (!$this->serviceDescription) {
                throw new \RuntimeException('No service description was set so '
                    .'the value cannot be formatted.');
            }

            return $this->serviceDescription->format($this->format, $value);
        }

        // Convert Boolean values
        if ($this->type == 'boolean' && !is_bool($value)) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        // Apply filters to the value
        if ($this->filters) {
            foreach ($this->filters as $filter) {
                if (is_array($filter)) {
                    // Convert complex filters that hold value place holders
                    foreach ($filter['args'] as &$data) {
                        if ($data == '@value') {
                            $data = $value;
                        } elseif ($data == '@api') {
                            $data = $this;
                        }
                    }
                    $value = ($filter['method'])(...$filter['args']);
                } else {
                    $value = $filter($value);
                }
            }
        }

        return $value;
    }

    /**
     * Get the name of the parameter
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Set the name of the parameter
     *
     * @param string $name Name to set
     */
    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /**
     * Get the key of the parameter, where sentAs will supersede name if it is
     * set.
     */
    public function getWireName(): ?string
    {
        return $this->sentAs ?: $this->name;
    }

    /**
     * Get the type(s) of the parameter
     *
     * @return string|array
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get if the parameter is required
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Get the default value of the parameter
     *
     * @return string|null
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * Get the description of the parameter
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get the minimum acceptable value for an integer
     */
    public function getMinimum(): ?int
    {
        return $this->minimum;
    }

    /**
     * Get the maximum acceptable value for an integer
     */
    public function getMaximum(): ?int
    {
        return $this->maximum;
    }

    /**
     * Get the minimum allowed length of a string value
     */
    public function getMinLength(): ?int
    {
        return $this->minLength;
    }

    /**
     * Get the maximum allowed length of a string value
     */
    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    /**
     * Get the maximum allowed number of items in an array value
     */
    public function getMaxItems(): ?int
    {
        return $this->maxItems;
    }

    /**
     * Get the minimum allowed number of items in an array value
     */
    public function getMinItems(): ?int
    {
        return $this->minItems;
    }

    /**
     * Get the location of the parameter
     */
    public function getLocation(): ?string
    {
        return $this->location;
    }

    /**
     * Get the sentAs attribute of the parameter that used with locations to
     * sentAs an attribute when it is being applied to a location.
     */
    public function getSentAs(): ?string
    {
        return $this->sentAs;
    }

    /**
     * Retrieve a known property from the parameter by name or a data property
     * by name. When no specific name value is passed, all data properties
     * will be returned.
     *
     * @param string|null $name Specify a particular property name to retrieve
     *
     * @return array|mixed|null
     */
    public function getData($name = null)
    {
        if (!$name) {
            return $this->data;
        } elseif (isset($this->data[$name])) {
            return $this->data[$name];
        } elseif (isset($this->{$name})) {
            return $this->{$name};
        }

        return null;
    }

    /**
     * Get whether or not the default value can be changed
     *
     * @return bool
     */
    public function isStatic()
    {
        return $this->static;
    }

    /**
     * Get an array of filters used by the parameter
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Get the properties of the parameter
     *
     * @return Parameter[]
     */
    public function getProperties(): array
    {
        if (!$this->propertiesCache) {
            $this->propertiesCache = [];
            foreach (array_keys($this->properties) as $name) {
                $this->propertiesCache[$name] = $this->getProperty($name);
            }
        }

        return $this->propertiesCache;
    }

    /**
     * Get a specific property from the parameter
     *
     * @param string $name Name of the property to retrieve
     */
    public function getProperty(string $name): ?Parameter
    {
        if (!isset($this->properties[$name])) {
            return null;
        }

        if (!$this->properties[$name] instanceof self) {
            $this->properties[$name]['name'] = $name;
            $this->properties[$name] = new static(
                $this->properties[$name],
                ['description' => $this->serviceDescription]
            );
        }

        return $this->properties[$name];
    }

    /**
     * Get the additionalProperties value of the parameter
     *
     * @return bool|Parameter|null
     */
    public function getAdditionalProperties()
    {
        if (is_array($this->additionalProperties)) {
            $this->additionalProperties = new static(
                $this->additionalProperties,
                ['description' => $this->serviceDescription]
            );
        }

        return $this->additionalProperties;
    }

    /**
     * Get the item data of the parameter
     */
    public function getItems(): ?Parameter
    {
        if (is_array($this->items)) {
            $this->items = new static(
                $this->items,
                ['description' => $this->serviceDescription]
            );
        }

        return $this->items;
    }

    /**
     * Get the enum of strings that are valid for the parameter
     */
    public function getEnum(): ?array
    {
        return $this->enum;
    }

    /**
     * Get the regex pattern that must match a value when the value is a string
     */
    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    /**
     * Get the format attribute of the schema
     */
    public function getFormat(): ?string
    {
        return $this->format;
    }

    /**
     * Set the array of filters used by the parameter
     *
     * @param array $filters Array of functions to use as filters
     */
    private function setFilters(array $filters): self
    {
        $this->filters = [];
        foreach ($filters as $filter) {
            $this->addFilter($filter);
        }

        return $this;
    }

    /**
     * Add a filter to the parameter
     *
     * @param mixed $filter Method to filter the value through
     *
     * @throws \InvalidArgumentException
     */
    private function addFilter($filter): self
    {
        if (is_string($filter)) {
            $this->filters[] = $filter;

            return $this;
        }

        if (!is_array($filter)) {
            throw new \InvalidArgumentException(\sprintf('Filters must be strings or complex filter arrays; got %s.', get_debug_type($filter)));
        }

        if (!isset($filter['method'])) {
            throw new \InvalidArgumentException(
                'A [method] value must be specified for each complex filter'
            );
        }

        if (!isset($filter['args']) || !is_array($filter['args'])) {
            throw new \InvalidArgumentException(\sprintf('An [args] array must be specified for each complex filter; got %s.', get_debug_type($filter['args'] ?? null)));
        }

        $this->filters[] = $filter;

        return $this;
    }

    /**
     * Check if a parameter has a specific variable and if it set.
     */
    public function has(string $var): bool
    {
        if (!isset($this->{$var})) {
            return false;
        }

        // The required and static bool properties initialize to false, so their
        // presence must come from the resolved schema data instead of isset().
        if (($var === 'required' || $var === 'static') && !array_key_exists($var, $this->resolvedData)) {
            return false;
        }

        $value = $this->{$var};

        return $value !== '' && $value !== [];
    }
}
