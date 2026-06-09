# Service Client Extension Points

`GuzzleHttp\Command\Guzzle\GuzzleClient` extends `guzzlehttp/command`'s service
client and accepts optional callables for command serialization and response
deserialization. The third constructor argument is a callable invoked as
`callable(GuzzleHttp\Command\CommandInterface): Psr\Http\Message\RequestInterface`.
The fourth constructor argument is a callable invoked as
`callable(Psr\Http\Message\ResponseInterface, Psr\Http\Message\RequestInterface, GuzzleHttp\Command\CommandInterface): GuzzleHttp\Command\ResultInterface`.

Any PHP callable is accepted, including invokable objects. The built-in
`Serializer` and `Deserializer` classes are invokable, so they can be passed
directly when you need custom request or response locations.

The fifth constructor argument accepts a command `GuzzleHttp\HandlerStack` whose
handlers follow the command handler contract
`callable(GuzzleHttp\Command\CommandInterface): GuzzleHttp\Promise\PromiseInterface<GuzzleHttp\Command\ResultInterface, mixed>`.
The sixth constructor argument is the service client configuration array. Known
keys include `defaults`, `validate`, `process`, and `response_locations`.
