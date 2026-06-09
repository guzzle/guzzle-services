# Service Descriptions

Guzzle Services builds command-based clients from service description arrays. A description maps operation names and parameters to HTTP requests, then maps responses into result data.

## Basic Description

```php
use GuzzleHttp\Client;
use GuzzleHttp\Command\Guzzle\Description;
use GuzzleHttp\Command\Guzzle\GuzzleClient;

$description = new Description([
    'baseUri' => 'https://api.example.com',
    'operations' => [
        'getUser' => [
            'httpMethod' => 'GET',
            'uri' => '/users/{id}',
            'responseModel' => 'user',
            'parameters' => [
                'id' => ['type' => 'string', 'location' => 'uri'],
            ],
        ],
    ],
    'models' => [
        'user' => [
            'type' => 'object',
            'additionalProperties' => ['location' => 'json'],
        ],
    ],
]);

$client = new GuzzleClient(new Client(), $description);
$result = $client->getUser(['id' => '123']);
```

## Operations

Operations describe how a command becomes an HTTP request. Common operation keys include:

- `httpMethod`: the HTTP method to send
- `uri`: the URI template for the operation
- `parameters`: operation parameters and their request locations
- `responseModel`: the model used to process the response
- `process`: whether response processing should run for the operation

## Models

Models describe the shape of processed response data. A response model can read fields from JSON and expose them as result data.

Use `additionalProperties` when a JSON object should allow properties beyond a fixed schema.
