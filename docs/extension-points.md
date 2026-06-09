# Extension Points

`GuzzleHttp\Command\Guzzle\GuzzleClient` extends `guzzlehttp/command`'s service client and accepts optional callables for command serialization and response deserialization.

## Serializer

The third constructor argument is a callable invoked as:

```php
callable(GuzzleHttp\Command\CommandInterface): Psr\Http\Message\RequestInterface
```

The built-in `Serializer` class is invokable, so it can be passed directly when you need custom request locations.

## Deserializer

The fourth constructor argument is a callable invoked as:

```php
callable(
    Psr\Http\Message\ResponseInterface,
    Psr\Http\Message\RequestInterface,
    GuzzleHttp\Command\CommandInterface
): GuzzleHttp\Command\ResultInterface
```

The built-in `Deserializer` class is invokable, so it can be passed directly when you need custom response locations.

## Command Handler Stack

The fifth constructor argument accepts a command `GuzzleHttp\HandlerStack`. Handlers follow this contract:

```php
callable(GuzzleHttp\Command\CommandInterface): GuzzleHttp\Promise\PromiseInterface
```

Use command middleware for command-level behavior. Use HTTP middleware on the underlying Guzzle client for PSR-7 request and response behavior.

## Client Configuration

The sixth constructor argument is the service client configuration array. Known keys include `defaults`, `validate`, `process`, and `response_locations`.
