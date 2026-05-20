Guzzle Services Upgrade Guide
=============================

2.0 from 1.x
------------

Guzzle Services 2.0 is a major release that removes deprecated service
description aliases, enables strict types, raises the minimum PHP version, and
updates the Guzzle dependency stack. Applications that use current 1.x service
description keys should usually need small changes. Applications that still use
legacy aliases, depend on older Guzzle dependencies, or provide custom filters
and extension points need closer review.

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

#### Operation HTTP Methods

Operations without an `httpMethod` now default to `GET` in
`GuzzleHttp\Command\Guzzle\Operation`, matching the request method already used
by the serializer at runtime.

This changes the public operation data returned by `getHttpMethod()` and
`toArray()`:

```php
$operation = new Operation();

// 1.x
$operation->getHttpMethod(); // ''
$operation->toArray()['httpMethod']; // ''

// 2.0
$operation->getHttpMethod(); // 'GET'
$operation->toArray()['httpMethod']; // 'GET'
```

Explicit `httpMethod` values must now be non-empty strings. Passing an empty
string or a non-string value throws `InvalidArgumentException`.

#### Header Location Values

Header location values must now be strings or arrays of strings. Guzzle Services
1.x accepted scalar header values and cast them to strings.

Normalize header values before constructing commands if your application passes
integers, floats, booleans, or other non-string values into header locations.

#### Strict Types and Extension Points

Guzzle Services source and test files now declare strict types. This mostly
affects calls made by Guzzle Services into extension points, especially custom
parameter filters. Custom code does not become strict unless it also declares
strict types, but scalar arguments passed from strict Guzzle Services files are
no longer weakly coerced for typed filter callables.

For example, this filter accepted integer command values in 1.x because the
value was weakly coerced to a string before the filter was called:

```php
final class Filters
{
    public static function normalizeId(string $value): string
    {
        return trim($value);
    }
}
```

In 2.0, the same filter throws `TypeError` if the command value is an integer.
Normalize values before creating the command, or make the filter accept the
actual values it may receive and normalize explicitly:

```php
final class Filters
{
    public static function normalizeId($value): string
    {
        return trim((string) $value);
    }
}
```

Review custom request locations, response locations, serializers, deserializers,
schema validators, and formatters for the same pattern. If custom code passes
scalars or stringable objects to PHP internal functions such as `json_decode()`,
`parse_str()`, `preg_match()`, `strlen()`, or `XMLWriter` methods, cast values
explicitly before calling those functions.

#### Native Signatures

Guzzle Services 2.0 adds native parameter, property, and return types to public
interfaces, request and response locations, serializers, deserializers,
validators, and formatters. Custom implementations and subclasses must update
method signatures to remain compatible.

Service description values should use the documented PHP types, such as strings
for names and URIs, booleans for flags, and integers for min/max constraints.

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

1.0 from 0.6
------------

Guzzle Services 1.0 added support for Guzzle 6 and PSR-7. Applications that use
only service descriptions should usually need small changes. Applications with
custom request locations, response locations, or subscribers need closer review.

#### Dependencies

Guzzle Services 1.0 added support for Guzzle 6. It requires PHP 5.5 or higher,
`guzzlehttp/guzzle` 6.2 or higher, and `guzzlehttp/command` 1.x.

If your application still uses Guzzle 5, continue using Guzzle Services 0.6.

#### Service Description Base URI

Use `baseUri` instead of `baseUrl` in service descriptions.

```php
// 0.6
$description = new Description([
    'baseUrl' => 'https://api.example.com',
]);

// 1.0
$description = new Description([
    'baseUri' => 'https://api.example.com',
]);
```

#### Request Locations

The `postField` and `postFile` request locations were renamed to `formParam`
and `multipart`.

```php
// 0.6
[
    'parameters' => [
        'name' => [
            'type' => 'string',
            'location' => 'postField',
        ],
        'avatar' => [
            'type' => 'string',
            'location' => 'postFile',
        ],
    ],
]

// 1.0
[
    'parameters' => [
        'name' => [
            'type' => 'string',
            'location' => 'formParam',
        ],
        'avatar' => [
            'type' => 'string',
            'location' => 'multipart',
        ],
    ],
]
```

#### Custom Locations and Subscribers

Guzzle Services 1.0 serializes PSR-7 requests for Guzzle 6. Custom request or
response location implementations should use PSR-7 message interfaces and return
the modified message instead of mutating the message in place.

The old subscriber-based response processing and input validation hooks were
replaced by Guzzle Command handlers. Move custom validation or processing logic
to command handlers when upgrading.
