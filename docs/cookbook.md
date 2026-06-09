# Cookbook

This page contains focused examples for common Guzzle Services configuration tasks.

## Disabling Response Processing for an Operation

By default, responses are parsed according to the operation's `responseModel`. For operations that return binary data or another response body that should not be parsed, set `process` to `false` on that operation.

```php
$description = new Description([
    'operations' => [
        'getMetadata' => [
            'httpMethod' => 'GET',
            'uri' => '/metadata/{id}',
            'responseModel' => 'metadataResponse',
        ],
        'getFile' => [
            'httpMethod' => 'GET',
            'uri' => '/files/{id}',
            'process' => false,
        ],
    ],
]);
```

When response processing is disabled, the raw PSR-7 response is returned in the result's `response` key.

```php
$result = $guzzleClient->getFile(['id' => 123]);
$response = $result['response'];
```

Operation-level `process` overrides the client-level `process` option. If an operation omits `process`, or sets it to `null`, it inherits the client setting.

## Modeling JSON Response Fields with Multiple Types

Some JSON APIs return the same field with different types depending on the data. Model this by setting `type` to an array of allowed types.

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
                    'items' => ['type' => 'string'],
                ],
            ],
        ],
    ],
]);
```

## Changing Query Parameter Serialization

By default, query params are serialized using strict RFC 3986 rules with `http_build_query`. Array params are serialized with numeric indexes.

```php
$client->myMethod(['foo' => ['bar', 'baz']]);
// foo[0]=bar&foo[1]=baz
```

Some APIs require query params like `foo[]=bar&foo[]=baz`. Override the `query` request location with a serializer configured for that behavior.

```php
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\QuerySerializer\Rfc3986Serializer;
use GuzzleHttp\Command\Guzzle\RequestLocation\QueryLocation;
use GuzzleHttp\Command\Guzzle\Serializer;

$queryLocation = new QueryLocation('query', new Rfc3986Serializer(true));
$serializer = new Serializer($description, ['query' => $queryLocation]);
$guzzleClient = new GuzzleClient($client, $description, $serializer);
```
