# Cookbook

## Disabling Response Processing for an Operation

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

## Changing Query Parameter Serialization

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
