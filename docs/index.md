# Guzzle Services Documentation

Provides an implementation of the Guzzle Command library that uses Guzzle service descriptions to describe web services, serialize requests, and parse responses into easy to use model structures.

```php
use GuzzleHttp\Client;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\Description;

$client = new Client();
$description = new Description([
	'baseUri' => 'http://httpbin.org/',
	'operations' => [
		'testing' => [
			'httpMethod' => 'GET',
			'uri' => '/get{?foo}',
			'responseModel' => 'getResponse',
			'parameters' => [
				'foo' => [
					'type' => 'string',
					'location' => 'uri'
				],
				'bar' => [
					'type' => 'string',
					'location' => 'query'
				]
			]
		]
	],
	'models' => [
		'getResponse' => [
			'type' => 'object',
			'additionalProperties' => [
				'location' => 'json'
			]
		]
	]
]);

$guzzleClient = new GuzzleClient($client, $description);

$result = $guzzleClient->testing(['foo' => 'bar']);
echo $result['args']['foo'];
// bar
```

## Installing

This project can be installed using Composer:

```bash
composer require guzzlehttp/guzzle-services
```

## Version Guidance

| Version | Status       | PHP Version  |
|---------|--------------|--------------|
| 2.x     | Experimental | >=7.4,<8.6   |
| 1.x     | Latest       | >=7.2.5,<8.6 |

See [UPGRADING.md](../UPGRADING.md) for upgrade notes.

## Cookbook

### Disabling response processing for an operation

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
		],
		'getFile' => [
			'httpMethod' => 'GET',
			'uri' => '/files/{id}',
			'process' => false,
		]
	]
]);
```

When response processing is disabled, the raw PSR-7 response is returned in the
result's `response` key:

```php
$result = $guzzleClient->getFile(['id' => 123]);
$response = $result['response'];
```

Operation-level `process` overrides the client-level `process` option. If an
operation omits `process`, or sets it to `null`, it inherits the client setting.

### Modeling JSON response fields with multiple types

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
		]
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

### Changing the way query params are serialized

By default, query params are serialized using strict RFC3986 rules, using `http_build_query` method. With this, array params are serialized this way:

```php
$client->myMethod(['foo' => ['bar', 'baz']]);

// Query params will be foo[0]=bar&foo[1]=baz
```

However, a lot of APIs in the wild require the numeric indices to be removed, so that the query params end up being `foo[]=bar&foo[]=baz`. You
can easily change the behaviour by creating your own serializer and overriding the "query" request location:

```php
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\RequestLocation\QueryLocation;
use GuzzleHttp\Command\Guzzle\QuerySerializer\Rfc3986Serializer;
use GuzzleHttp\Command\Guzzle\Serializer;

$queryLocation = new QueryLocation('query', new Rfc3986Serializer(true));
$serializer = new Serializer($description, ['query' => $queryLocation]);
$guzzleClient = new GuzzleClient($client, $description, $serializer);
```

You can also create your own serializer if you have specific needs.

### Service client extension points

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

## Security

If you discover a security vulnerability within this package, please send an email to security@tidelift.com. All security vulnerabilities will be promptly addressed. Please do not disclose security-related issues publicly until a fix has been announced. Please see [Security Policy](https://github.com/guzzle/guzzle-services/security/policy) for more information.

## License

Guzzle is made available under the MIT License (MIT). Please see [License File](../LICENSE) for more information.

## For Enterprise

Available as part of the Tidelift Subscription

The maintainers of Guzzle and thousands of other packages are working with Tidelift to deliver commercial support and maintenance for the open source dependencies you use to build your applications. Save time, reduce risk, and improve code health, while paying the maintainers of the exact dependencies you use. [Learn more.](https://tidelift.com/subscription/pkg/packagist-guzzlehttp-guzzle-services?utm_source=packagist-guzzlehttp-guzzle-services&utm_medium=referral&utm_campaign=enterprise&utm_term=repo)
