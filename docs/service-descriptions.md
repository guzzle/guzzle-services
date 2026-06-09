# Service Descriptions

Service descriptions are arrays consumed by `GuzzleHttp\Command\Guzzle\Description`, `Operation`, `Parameter`, `Serializer`, and `Deserializer`. They describe an API in terms of named commands, how command input becomes an HTTP request, and how an HTTP response becomes a command result. This page covers the Guzzle Services description format; the lower-level command client lifecycle is documented in [Guzzle Command service clients](https://github.com/guzzle/command/blob/2.0/docs/service-clients.md).

```php
use GuzzleHttp\Client;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Exception\CommandException;
use GuzzleHttp\Command\Guzzle\GuzzleClient;

class ConflictException extends CommandException {}

$description = new Description([
    'name' => 'Example API',
    'apiVersion' => '2026-06-09',
    'description' => 'Example service-description-driven API client.',
    'baseUri' => 'https://api.example.com',
    'operations' => [
        'getUser' => [
            'httpMethod' => 'GET',
            'uri' => '/users/{id}',
            'responseModel' => 'userResponse',
            'parameters' => [
                'id' => [
                    'type' => 'string',
                    'required' => true,
                    'location' => 'uri',
                ],
                'include' => [
                    'type' => 'array',
                    'location' => 'query',
                    'items' => ['type' => 'string'],
                ],
            ],
        ],
        'createUser' => [
            'httpMethod' => 'POST',
            'uri' => '/users',
            'responseModel' => 'userResponse',
            'parameters' => [
                'name' => [
                    'type' => 'string',
                    'required' => true,
                    'location' => 'json',
                ],
                'role' => [
                    'type' => 'string',
                    'default' => 'reader',
                    'location' => 'json',
                ],
            ],
            'errorResponses' => [
                [
                    'code' => 409,
                    'phrase' => 'Conflict',
                    'class' => ConflictException::class,
                ],
            ],
        ],
    ],
    'models' => [
        'user' => [
            'type' => 'object',
            'location' => 'json',
            'properties' => [
                'id' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'email' => ['type' => ['null', 'string']],
            ],
            'additionalProperties' => true,
        ],
        'userResponse' => [
            'type' => 'object',
            'properties' => [
                'user' => ['$ref' => 'user', 'location' => 'json'],
                'requestId' => [
                    'type' => 'string',
                    'location' => 'header',
                    'sentAs' => 'X-Request-Id',
                ],
                'statusCode' => [
                    'type' => 'integer',
                    'location' => 'statusCode',
                ],
            ],
        ],
    ],
]);

$client = new GuzzleClient(new Client(), $description);
$result = $client->getUser(['id' => '123', 'include' => ['teams']]);
```

## Description Shape

The top-level description array supports these common keys:

| Key | Purpose |
| --- | --- |
| `name` | Optional API name exposed by `Description::getName()`. |
| `apiVersion` | Optional API version exposed by `Description::getApiVersion()`. |
| `description` | Optional API summary exposed by `Description::getDescription()`. |
| `baseUri` | Base URI used when resolving operation URIs. |
| `operations` | Map of operation names to operation definitions. |
| `models` | Map of reusable model names to parameter/model schemas. |

Unknown top-level keys are retained as extra data and can be read with `Description::getData()`.

## Base URI

`baseUri` is converted to a PSR-7 URI and combined with each operation `uri`. Relative operation URIs are resolved against `baseUri`; absolute operation URIs replace it. If an operation sets `uri` to `null`, the request is created directly from `baseUri`.

## Operations

Each operation becomes a command that can be executed with `$client->operationName([...])`, `$client->operationNameAsync([...])`, or `$client->getCommand('operationName', [...])`.

| Key | Purpose |
| --- | --- |
| `extends` | Name of another operation to inherit from. Operation keys are inherited, and `parameters` are merged one level deep. |
| `httpMethod` | HTTP method. Defaults to `GET`. |
| `uri` | URI template resolved against `baseUri`. Defaults to an empty string. |
| `parameters` | Map of command input names to parameter schemas. |
| `additionalParameters` | Schema for command input not listed in `parameters`. Useful for pass-through query, header, JSON, XML, or form fields. |
| `responseModel` | Name of a model used to parse the response into a result. |
| `process` | `true`, `false`, or `null`. `null` inherits the client-level `process` setting. |
| `deprecated` | Boolean flag exposed by `Operation::getDeprecated()`. |
| `errorResponses` | List of HTTP error mappings with `code`, optional `phrase`, and exception `class`. |

Operations also accept descriptive keys such as `summary`, `notes`, `documentationUrl`, and `data`. Values in `data` are available to custom serializers and locations through `Operation::getData()`.

## URI Templates

Operation `uri` values are expanded with `guzzlehttp/uri-template`. Only parameters with `location` set to `uri` are provided to URI template expansion. Use `location: query` when you want Guzzle Services to append query parameters after template expansion, or use URI Template query expressions like `{?filter*}` with `location: uri` when the template should control query expansion.

```php
'operations' => [
    'searchUsers' => [
        'httpMethod' => 'GET',
        'uri' => '/users{?filter*,page}',
        'parameters' => [
            'filter' => ['type' => 'object', 'location' => 'uri'],
            'page' => ['type' => 'integer', 'location' => 'uri'],
        ],
    ],
]
```

See [URI Template usage](https://github.com/guzzle/uri-template/blob/2.0/docs/uri-template-usage.md) for supported RFC 6570 expressions and [URI Template input contract](https://github.com/guzzle/uri-template/blob/2.0/docs/input-contract.md) for accepted variable values.

## Parameters and Models

Operation parameters and models use the same schema class, `GuzzleHttp\Command\Guzzle\Parameter`. A model is a named reusable schema in the top-level `models` map. A parameter is an operation input schema in an operation's `parameters` map.

| Key | Purpose |
| --- | --- |
| `type` | Expected value type. Supported types include `string`, `number`, `integer`, `boolean`, `object`, `array`, `numeric`, `null`, and `any`. Use an array for a union type, such as `['null', 'string']`. |
| `required` | When `true`, validation fails if the value is missing and no default/static value supplies it. |
| `default` | Value used when no value is supplied. |
| `static` | When `true`, the `default` value is always used and user input cannot override it. |
| `location` | Request or response location used by serializers/deserializers. |
| `sentAs` | Wire name. Use this when the command/result key differs from the HTTP header, query key, JSON key, or XML element name. |
| `filters` | List of callables, or complex filter arrays with `method` and `args`, applied by request serialization and response processing. The validation middleware also applies filters to present truthy values before validating them. |
| `format` | Named formatter used by request serialization and response processing, with limited pre-validation formatting for present truthy values. Supported values include `date-time`, `date`, `time`, `timestamp`, `date-time-http`, and `boolean-string`. |
| `properties` | Object child schemas keyed by logical property name. |
| `items` | Array item schema. |
| `additionalProperties` | `true`, `false`, or a schema for object properties not listed in `properties`. Object models default to allowing additional properties. |
| `$ref` | Reference to a top-level model. The referenced model is resolved into the parameter. |
| `extends` | Name of a top-level model to inherit from. Local schema keys override inherited keys. Nested maps such as `properties` are not deep-merged. |

Schemas also support validation-oriented keys such as `enum`, `pattern`, `minimum`, `maximum`, `minLength`, `maxLength`, `minItems`, and `maxItems`.

## Request Locations

Request locations are used by operation parameters during serialization.

| Location | Behavior |
| --- | --- |
| `uri` | Provides variables for operation URI template expansion. It is not a visitor location and does not run through `Serializer` request locations. |
| `query` | Adds values to the query string using the configured query serializer. |
| `header` | Adds a request header. Values must be strings or non-empty arrays of strings. |
| `body` | Appends `name=value` pairs to the request body. |
| `json` | Builds a JSON request body and sets `Content-Type: application/json` when no content type is already present. |
| `xml` | Builds an XML request body and sets `Content-Type: application/xml` when no content type is already present. XML-specific options are read from schema/operation `data`. |
| `formParam` | Builds an `application/x-www-form-urlencoded` request body. |
| `multipart` | Builds a multipart request body and sets a multipart content type with boundary when no content type is already present. |

## Models

Models are reusable parameter schemas. A response model must have top-level `type` set to `object` or `array`; other response model types are rejected by the deserializer.

```php
'models' => [
    'baseJsonObject' => [
        'type' => 'object',
        'location' => 'json',
        'additionalProperties' => false,
    ],
    'user' => [
        'extends' => 'baseJsonObject',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
        ],
    ],
]
```

## Response Models

Response model properties use response locations to pull data from the PSR-7 response.

| Location | Behavior |
| --- | --- |
| `json` | Decodes a JSON object or array response body and maps properties by `sentAs` or property name. |
| `xml` | Parses an XML response body and maps elements or attributes by `sentAs` or property name. |
| `header` | Reads a response header by `sentAs` or property name. |
| `body` | Stores the full response body string in the result property. |
| `statusCode` | Stores the integer response status code. |
| `reasonPhrase` | Stores the response reason phrase. |

If an operation has no `responseModel`, response processing returns an empty `GuzzleHttp\Command\Result`. If `process` is disabled, response processing returns a result with the raw PSR-7 response in the `response` key.

## Defaults and Validation

`GuzzleClient` merges client `defaults` into command input when commands are created. Explicit command arguments win over defaults.

Validation is enabled by default. It checks required values, types, object properties, additional properties, array items, ranges, lengths, enums, and patterns. It also applies parameter `default` and `static` values. Request serialization and response processing apply the full `filters` and `format` pipeline; validation does limited pre-validation filtering/formatting for present truthy values.

```php
$client = new GuzzleClient($httpClient, $description, null, null, null, [
    'defaults' => ['apiKey' => getenv('EXAMPLE_API_KEY')],
    'validate' => true,
]);
```

The `validate` option installs a command handler during construction. Changing `validate` with `setConfig()` after construction does not add or remove that handler.

## Response Processing

Response processing is enabled by default. Set client config `process` to `false` to return raw responses for all operations, or set operation `process` to override the client setting for a single operation. Operation `process: null` inherits the client setting.

```php
'operations' => [
    'downloadFile' => [
        'httpMethod' => 'GET',
        'uri' => '/files/{id}',
        'process' => false,
        'parameters' => [
            'id' => ['type' => 'string', 'required' => true, 'location' => 'uri'],
        ],
    ],
]
```

The client-level `process` option is used when the default deserializer is constructed. Changing it with `setConfig()` after construction does not replace the deserializer.

## Error Responses

`errorResponses` maps specific HTTP responses to exception classes. Each entry must include `code`, may include `phrase`, and must include `class`. If both `code` and `phrase` are set, both must match. If only `code` is set, the status code match is enough.

```php
'errorResponses' => [
    [
        'code' => 404,
        'class' => UserNotFoundException::class,
    ],
    [
        'code' => 409,
        'phrase' => 'Conflict',
        'class' => ConflictException::class,
    ],
]
```

Exception classes should extend `GuzzleHttp\Command\Exception\CommandException` when you want the custom exception class to propagate from the command layer. Other compatible exception classes are wrapped by the command layer. `errorResponses` are checked only when response processing is enabled. Unmatched HTTP errors can still be raised by the underlying Guzzle HTTP client when `http_errors` is enabled.

## Related

- [README](../README.md)
- [Cookbook](cookbook.md)
- [Service Client Extension Points](service-client-extension-points.md)
- [Upgrade Guide](../UPGRADING.md)
- [Changelog](../CHANGELOG.md)
- [Guzzle Command service clients](https://github.com/guzzle/command/blob/2.0/docs/service-clients.md)
- [URI Template usage](https://github.com/guzzle/uri-template/blob/2.0/docs/uri-template-usage.md)
- [URI Template input contract](https://github.com/guzzle/uri-template/blob/2.0/docs/input-contract.md)
