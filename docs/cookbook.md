# Cookbook

This cookbook collects focused recipes for common Guzzle Services tasks. It
assumes you already have a `GuzzleHttp\Command\Guzzle\Description` and a
`GuzzleHttp\Command\Guzzle\GuzzleClient`; see
[Service Descriptions](service-descriptions.md) for the full description format.

## Returning Raw Responses

By default, responses are parsed according to the operation's `responseModel`.
For operations that return binary data or another response body that should not
be parsed, set `process` to `false` on that operation:

```php
$description = new Description([
    'operations' => [
        'getMetadata' => [
            'httpMethod' => 'GET',
            'uri' => '/metadata/{id}',
            'responseModel' => 'metadataResponse',
            'parameters' => [
                'id' => [
                    'type' => 'string',
                    'required' => true,
                    'location' => 'uri',
                ],
            ],
        ],
        'getFile' => [
            'httpMethod' => 'GET',
            'uri' => '/files/{id}',
            'process' => false,
            'parameters' => [
                'id' => [
                    'type' => 'string',
                    'required' => true,
                    'location' => 'uri',
                ],
            ],
        ],
    ],
]);
```

When response processing is disabled, the raw PSR-7 response is returned in the
result's `response` key:

```php
$result = $guzzleClient->getFile(['id' => '123']);
$response = $result['response'];
```

Operation-level `process` overrides the client-level `process` option. If an
operation omits `process`, or sets it to `null`, it inherits the client setting.

## Adding Client Defaults

Use client `defaults` for command parameters that should be present on most or
all commands, such as an API key, tenant ID, or shared query flag. Explicit
command arguments override defaults.

```php
use GuzzleHttp\Client;
use GuzzleHttp\Command\Guzzle\GuzzleClient;

$guzzleClient = new GuzzleClient(
    new Client(),
    $description,
    null,
    null,
    null,
    [
        'defaults' => [
            'apiKey' => getenv('EXAMPLE_API_KEY'),
        ],
    ]
);

$result = $guzzleClient->listUsers();
```

The default still needs a matching parameter schema, or an operation-level
`additionalParameters` schema, so built-in validation and serialization know how
to handle it:

```php
'parameters' => [
    'apiKey' => [
        'type' => 'string',
        'location' => 'query',
        'sentAs' => 'api_key',
    ],
]
```

## Modeling JSON Response Fields with Multiple Types

Some JSON APIs return the same field with different types depending on the data.
For example, a response field might be `null`, a string, or an array of strings.
Model this by setting `type` to an array of allowed types:

```php
$description = new Description([
    'operations' => [
        'getMetadata' => [
            'httpMethod' => 'GET',
            'uri' => '/metadata/{id}',
            'responseModel' => 'metadataResponse',
        ],
    ],
    'models' => [
        'metadataResponse' => [
            'type' => 'object',
            'location' => 'json',
            'properties' => [
                'value' => [
                    'type' => ['null', 'string', 'array'],
                    'items' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ],
    ],
]);
```

## Changing Query Parameter Serialization

By default, query parameters are serialized with strict RFC 3986 encoding using
PHP-style array indexes. Array query parameters are serialized like this:

```php
$client->myMethod(['foo' => ['bar', 'baz']]);

// Query parameters will be foo%5B0%5D=bar&foo%5B1%5D=baz
```

Some APIs require numeric indexes to be removed, so the query string becomes
`foo%5B%5D=bar&foo%5B%5D=baz`. Change this behavior by creating a query location
with a different serializer and passing it to the request serializer:

```php
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\RequestLocation\QueryLocation;
use GuzzleHttp\Command\Guzzle\QuerySerializer\Rfc3986Serializer;
use GuzzleHttp\Command\Guzzle\Serializer;

$queryLocation = new QueryLocation('query', new Rfc3986Serializer(true));
$serializer = new Serializer($description, ['query' => $queryLocation]);
$guzzleClient = new GuzzleClient($client, $description, $serializer);
```

You can also implement your own query serializer or replace the `query` request
location if your API needs a different query format.

## Related

- [README](../README.md)
- [Service Descriptions](service-descriptions.md)
- [Service Client Extension Points](service-client-extension-points.md)
- [Upgrade Guide](../UPGRADING.md)
- [Changelog](../CHANGELOG.md)
