# Guzzle Services

`guzzlehttp/guzzle-services` builds service-description-driven clients on top of [`guzzlehttp/command`](https://github.com/guzzle/command). A service description maps named operations and parameters to HTTP requests, then maps responses into result data.

Use this package when you are building an SDK-style client for an API and want operations described in arrays instead of hand-writing every request serializer. If you only need to send HTTP requests directly, install [`guzzlehttp/guzzle`](https://github.com/guzzle/guzzle) instead.

## Installation

```bash
composer require guzzlehttp/guzzle-services
```

## Quick Start

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

## Documentation

- [Full documentation](docs/index.md)
- [Cookbook](docs/index.md#cookbook)
- [Response processing](docs/index.md#disabling-response-processing-for-an-operation)
- [Query serialization](docs/index.md#changing-the-way-query-params-are-serialized)
- [Extension points](docs/index.md#service-client-extension-points)
- [Upgrade guide](UPGRADING.md)

## Version Guidance

| Version | Status       | PHP Version  |
|---------|--------------|--------------|
| 2.x     | Experimental | >=7.4,<8.6   |
| 1.x     | Latest       | >=7.2.5,<8.6 |

## Security

If you discover a security vulnerability within this package, please send an email to security@tidelift.com. All security vulnerabilities will be promptly addressed. Please do not disclose security-related issues publicly until a fix has been announced. Please see [Security Policy](https://github.com/guzzle/guzzle-services/security/policy) for more information.

## License

Guzzle is made available under the MIT License (MIT). Please see [License File](LICENSE) for more information.

## For Enterprise

Available as part of the Tidelift Subscription

The maintainers of Guzzle and thousands of other packages are working with Tidelift to deliver commercial support and maintenance for the open source dependencies you use to build your applications. Save time, reduce risk, and improve code health, while paying the maintainers of the exact dependencies you use. [Learn more.](https://tidelift.com/subscription/pkg/packagist-guzzlehttp-guzzle-services?utm_source=packagist-guzzlehttp-guzzle-services&utm_medium=referral&utm_campaign=enterprise&utm_term=repo)
