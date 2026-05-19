Guzzle Services Upgrade Guide
=============================

1.x to 2.0
----------

#### PHP Version and Dependencies

Guzzle Services 2.0 requires PHP `^7.4 || ^8.0`. Guzzle Services 1.x supported
PHP `^7.2.5 || ^8.0`.

Guzzle Services 2.0 also requires Guzzle Command 2.x, Guzzle 8.x, Guzzle PSR-7
3.x, and Guzzle URI Template 2.x.

If your application still supports PHP 7.2 or 7.3, or still uses the Guzzle 7
dependency stack, continue using Guzzle Services 1.x until your minimum PHP and
dependency versions are raised.

#### Legacy Service Description Aliases

The legacy `baseUrl` service description option has been removed. Use `baseUri`
instead.

```php
// 1.x, no longer handled in 2.0
$description = new Description([
    'baseUrl' => 'https://api.example.com',
]);

// 2.0
$description = new Description([
    'baseUri' => 'https://api.example.com',
]);
```

The legacy `responseClass` operation option has also been removed. Use
`responseModel` instead.

```php
// 1.x, no longer handled in 2.0
$description = new Description([
    'operations' => [
        'GetUser' => [
            'responseClass' => 'User',
        ],
    ],
]);

// 2.0
$description = new Description([
    'operations' => [
        'GetUser' => [
            'responseModel' => 'User',
        ],
    ],
]);
```

In 2.0, `baseUrl` and `responseClass` are retained only as extra description or
operation data. They no longer configure request base URIs or response models.

#### Command Client Dependency

`GuzzleHttp\Command\Guzzle\GuzzleClient` continues to build on
`guzzlehttp/command`, but the required Command major version is now 2.x. Review
the Guzzle Command 2.0 upgrade guide if your application uses Command APIs
directly.

#### Service Descriptions and URIs

Guzzle PSR-7 3.x validates URI hosts, URI schemes, query values, and
iterator-backed stream chunks more strictly. Invalid `baseUri` values, operation
URI templates, request hosts, or unsupported query data may now fail earlier.

Guzzle URI Template 2.x drops PHP 7.2 and 7.3 support. URI template expansion is
otherwise expected to remain compatible with 1.x.

#### Downstream Upgrade Guides

Review the Guzzle 8, Guzzle Command 2.0, Guzzle PSR-7 3.0, Guzzle Promises 3.0,
and Guzzle URI Template 2.0 upgrade guides for dependency-level behavior changes.
