# Service Client Extension Points

`GuzzleHttp\Command\Guzzle\GuzzleClient` adapts Guzzle Command service clients
to service descriptions. It supplies default request serialization, response
deserialization, validation middleware, and client configuration, while leaving
each part replaceable. This page covers extension points specific to Guzzle
Services; the base command client concepts are documented in
[Guzzle Command service clients](https://github.com/guzzle/command/blob/2.0/docs/service-clients.md)
and
[Guzzle Command middleware](https://github.com/guzzle/command/blob/2.0/docs/middleware-extending-the-client.md).

## Constructor Extension Points

`GuzzleClient` accepts these constructor arguments:

```php
new GuzzleClient(
    ClientInterface $client,
    DescriptionInterface $description,
    ?callable $commandToRequestTransformer = null,
    ?callable $responseToResultTransformer = null,
    ?HandlerStack $commandHandlerStack = null,
    array $config = []
);
```

The third argument is invoked as:

```php
callable(GuzzleHttp\Command\CommandInterface): Psr\Http\Message\RequestInterface
```

The fourth argument is invoked as:

```php
callable(
    Psr\Http\Message\ResponseInterface,
    Psr\Http\Message\RequestInterface,
    GuzzleHttp\Command\CommandInterface
): GuzzleHttp\Command\ResultInterface
```

The fifth argument is a command `GuzzleHttp\HandlerStack` whose handlers follow
this shape:

```php
callable(
    GuzzleHttp\Command\CommandInterface
): GuzzleHttp\Promise\PromiseInterface<GuzzleHttp\Command\ResultInterface, mixed>
```

The sixth argument is the Guzzle Services client config array.

## Custom Request Locations

Request locations serialize operation parameters into parts of a PSR-7 request.
The built-in request locations are `query`, `header`, `body`, `json`, `xml`,
`formParam`, and `multipart`. The `uri` location is handled separately by URI
template expansion.

Pass custom locations to `Serializer` as an associative array keyed by location
name. Custom keys override built-in keys with the same name.

```php
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\RequestLocation\QueryLocation;
use GuzzleHttp\Command\Guzzle\QuerySerializer\Rfc3986Serializer;
use GuzzleHttp\Command\Guzzle\Serializer;

$serializer = new Serializer($description, [
    'query' => new QueryLocation('query', new Rfc3986Serializer(true)),
    'custom' => new CustomRequestLocation(),
]);

$client = new GuzzleClient($httpClient, $description, $serializer);
```

A custom request location implements
`GuzzleHttp\Command\Guzzle\RequestLocation\RequestLocationInterface`. `visit()`
is called for each matching parameter, and `after()` is called once after all
visited parameters for that location. `after()` is where locations usually
handle `additionalParameters`.

## Custom Response Locations

Response locations deserialize parts of a PSR-7 response into command result
fields. The built-in response locations are `json`, `xml`, `header`, `body`,
`statusCode`, and `reasonPhrase`.

When using the default deserializer, add or replace response locations with
client config:

```php
use GuzzleHttp\Command\Guzzle\GuzzleClient;

$client = new GuzzleClient(
    $httpClient,
    $description,
    null,
    null,
    null,
    [
        'response_locations' => [
            'csv' => new CsvResponseLocation(),
        ],
    ]
);
```

A custom response location implements
`GuzzleHttp\Command\Guzzle\ResponseLocation\ResponseLocationInterface`.
`before()` is called once before visiting matching model properties, `visit()`
is called for each matching property, and `after()` is called after all matching
properties are visited.

## Custom Serializer and Deserializer

`Serializer` and `Deserializer` are invokable objects, so they can be passed
directly to the `GuzzleClient` constructor. Pass a `Serializer` when you only
need to customize request locations; pass a `Deserializer` when you need to
customize response locations or response-processing behavior.

```php
use GuzzleHttp\Command\Guzzle\Deserializer;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\Serializer;

$serializer = new Serializer($description, [
    'custom' => new CustomRequestLocation(),
]);

$deserializer = new Deserializer($description, true, [
    'csv' => new CsvResponseLocation(),
]);

$client = new GuzzleClient(
    $httpClient,
    $description,
    $serializer,
    $deserializer
);
```

You can also pass any callable with the constructor signatures shown above. When
you provide a custom response transformer, `response_locations` and client-level
`process` config are not applied to it automatically; wire those concerns into
your transformer if you need them.

## Command Handler Stack

The command handler stack is separate from the underlying Guzzle HTTP handler
stack. Command middleware wraps commands before they are transformed into HTTP
requests; HTTP middleware belongs on the underlying `GuzzleHttp\ClientInterface`
instead.

```php
use GuzzleHttp\Command\CommandInterface;

$client->getHandlerStack()->push(function (callable $handler) {
    return function (CommandInterface $command) use ($handler) {
        $command['@http'] = ($command['@http'] ?: []) + [
            'timeout' => 2.0,
        ];

        return $handler($command);
    };
}, 'timeout');
```

The special `@http` command key is consumed by the base command client and
passed as request options to the underlying HTTP client's `sendAsync()` call.

## Client Config

`GuzzleClient` recognizes these config keys:

| Key | Effect |
| --- | --- |
| `defaults` | Default command input merged into each command when it is created. Explicit command input wins. |
| `validate` | Boolean. Enables or disables service-description validation middleware during construction. Defaults to `true`. |
| `process` | Boolean. Enables or disables default response processing during construction. Defaults to `true`. Operation `process` can override it. |
| `response_locations` | Map of response location names to `ResponseLocationInterface` instances used by the default deserializer. |

`defaults` is read when commands are created, so changing it with `setConfig()`
affects later commands. `validate`, `process`, and `response_locations` have
constructor-only effects in the default client because they install middleware
or build the default deserializer during construction.

## Constructor-Only `validate` and `process` Effects

Validation is implemented by pushing `ValidatedDescriptionHandler` onto the
command handler stack in the constructor. Calling `setConfig('validate', false)`
later only changes the stored config value; it does not remove the middleware
from the stack.

Response processing is captured when the default `Deserializer` is created in
the constructor. Calling `setConfig('process', false)` later only changes the
stored config value; it does not replace the deserializer. To change response
processing per operation, set the operation's `process` key. To change response
processing globally after construction, create a new client with the desired
config or pass your own response transformer.

## Related

- [README](../README.md)
- [Service Descriptions](service-descriptions.md)
- [Cookbook](cookbook.md)
- [Upgrade Guide](../UPGRADING.md)
- [Changelog](../CHANGELOG.md)
- [Guzzle Command service clients](https://github.com/guzzle/command/blob/2.0/docs/service-clients.md)
- [Guzzle Command middleware](https://github.com/guzzle/command/blob/2.0/docs/middleware-extending-the-client.md)
