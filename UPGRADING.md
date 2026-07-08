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

Guzzle Services 2.0 also requires
[Guzzle Command 2.x](https://github.com/guzzle/command/blob/2.0/UPGRADING.md),
[Guzzle 8.x](https://github.com/guzzle/guzzle/blob/8.0/UPGRADING.md),
[Guzzle PSR-7 3.x](https://github.com/guzzle/psr7/blob/3.0/UPGRADING.md), and
[Guzzle URI Template 2.x](https://github.com/guzzle/uri-template/blob/2.0/UPGRADING.md).
Guzzle Services 1.x supported Guzzle Command `^1.5`, Guzzle `^7.11`, Guzzle
PSR-7 `^2.11`, and Guzzle URI Template `^1.0.6`.

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

Explicit `httpMethod` casing is now preserved when requests are serialized.
Guzzle Services 2.0 uses Guzzle PSR-7 3.x, whose request implementation no
longer uppercases explicitly provided methods. If a service description sets
`httpMethod` to `get`, the serialized request method is `get`. Use `GET` in the
service description when the service expects an uppercase method name.

#### Operation Response Processing

`process` is now a first-class operation option. When set to `false`, response
model parsing is disabled for that operation. When set to `true`, response model
parsing is enabled for that operation even if the client-level `process` option
is disabled. When omitted or set to `null`, the operation inherits the client
setting.

Existing service descriptions that used a custom top-level operation key named
`process` should move that metadata under the operation's `data` key, or ensure
the value is a boolean or `null` and intentionally controls response processing.
Non-boolean `process` values now throw `InvalidArgumentException`.

#### Header Location Values

Header location values must now be strings or non-empty arrays of strings.
Guzzle Services 1.x accepted scalar header values and cast them to strings, and
accepted empty arrays as header values.

Normalize header values before constructing commands if your application passes
integers, floats, booleans, or other non-string values into header locations. Use
an empty string for an explicitly empty header value, or omit the command value
when no header should be sent.

#### Body Response Locations

Result values extracted with the `body` response location are now strings.
Guzzle Services 1.x stored the PSR-7 response body stream object and left
reading it to the caller, where methods such as `getContents()` returned only
the bytes after the current stream position. The 2.0 result value is always
the complete body, and custom parameter filters on `body`-located values now
receive that string:

```php
// 1.x
$contents = (string) $result['output'];

// 2.0
$contents = $result['output'];
```

#### JSON Response Bodies

JSON response processing now requires the top-level JSON value to be an object
or array. Scalar JSON bodies such as `null`, numbers, strings, and booleans now
throw `RuntimeException`. For endpoints that intentionally return scalar JSON,
disable response processing with `process: false` and read the raw PSR-7
response from the result's `response` key.

#### Null Schema Values

Schema parameters with `type` set to `'null'` now match only actual `null`
values. Guzzle Services 1.x treated all falsy values, including `false`, `0`,
`''`, and `[]`, as matching `null`.

If a service description accepts specific falsy values, list those value types
explicitly:

```php
// 1.x accepted false, 0, '', and [] as null.
['type' => 'null']

// 2.0: list every value shape that is accepted.
['type' => ['null', 'boolean']]
```

#### XML Request Text Serialization

XML request scalar element values containing `<`, `>`, or `&` are now
serialized as escaped text instead of CDATA sections. XML parsers see the same
text value, but applications or tests that compare raw XML request bodies may
need to update expected strings.

#### XML Response Depth Limit

XML response deserialization now rejects XML responses that exceed 512 nested
elements during response traversal or additional-property conversion. This
matches PHP's default `json_decode()` depth and protects applications from
deeply nested XML responses consuming excessive stack, CPU, or memory during
conversion.

Applications that consume trusted services with legitimately deeper XML can
register a custom XML response location with a higher traversal limit:

```php
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Command\Guzzle\ResponseLocation\XmlLocation;

$client = new GuzzleClient(
    $httpClient,
    $description,
    null,
    null,
    null,
    [
        'response_locations' => [
            'xml' => new XmlLocation('xml', 2048),
        ],
    ]
);
```

Only raise this limit for responses from trusted services. This limit guards the
recursive traversal and conversion stage after XML parsing; it is not a response
body size limit and does not relax libxml's own XML parser limits. Responses
that exceed libxml parser limits will still fail before response traversal.

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

#### String Parameter Validation

Integer values cast to strings by `castIntegerToStringType` are now validated
against the parameter's `enum`, `pattern`, and length constraints; Guzzle
Services 1.x skipped those checks for cast integers. Enum matching is also
strict: values match only enum entries of the same type, so string parameters
must declare enum entries as strings (`'1'` rather than `1`).

#### Stringable Command Values

When validation is enabled, command values for parameters with `type: string`
may be stringable objects. Successful validation casts those values to strings
and stores the normalized strings back on the command before the next handler
runs. Normalize these values before creating the command if later middleware
expects the original object instance.

#### Non-finite Float Command Values

Command values serialized into request locations now reject `NAN`, `INF`, and
`-INF` floats with an `InvalidArgumentException`. Guzzle Services 1.7
deprecated these values; provide finite numbers or preformatted strings
instead.

#### Client Configuration Option Types

`GuzzleClient` now throws an `InvalidArgumentException` when constructed with
invalid configuration option values: `defaults` must be an array, `validate`
and `process` must be booleans, and `response_locations` must be an array of
`ResponseLocationInterface` instances. Guzzle Services 1.7 deprecated these
values and passed them through.

#### Native Signatures

Guzzle Services 2.0 adds native parameter, property, and return types across
public service APIs, including interfaces, `GuzzleClient`, service description
objects, request and response locations, serializers, deserializers, validators,
and formatters. Custom implementations and subclasses must update method
signatures to remain compatible.

`GuzzleClient` command execution now returns `ResultInterface` values. When the
`process` client option is `false`, the raw PSR-7 response is available as the
`response` key of the returned result instead of being returned directly.

Service description values must use the documented PHP types, such as strings
for names and URIs, booleans for flags, arrays for schema collections, and
integers for min/max constraints. Parameter schema values that Guzzle Services
1.6 deprecated and normalized are rejected instead of being cast.

This applies to documented parameter schema keys such as `name`, `description`,
`location`, `sentAs`, `pattern`, `format`, `$ref`, `extends`, `required`,
`static`, `minimum`, `maximum`, `minLength`, `maxLength`, `minItems`,
`maxItems`, `filters`, `properties`, `data`, `enum`, `additionalProperties`,
`items`, and `type`. Other keys, including `default` and unknown custom keys,
are retained as parameter data without strict rejection.

#### Soft-Final Classes

`GuzzleHttp\Command\Guzzle\Operation`,
`GuzzleHttp\Command\Guzzle\Handler\ValidatedDescriptionHandler`, and
`GuzzleHttp\Command\Guzzle\QuerySerializer\Rfc3986Serializer` are now annotated
with `@final`.

Code extending `Operation` should model operation data in service descriptions
instead of subclassing the runtime operation object. Code extending
`ValidatedDescriptionHandler` should use command middleware composition instead.
Code extending `Rfc3986Serializer` should implement `QuerySerializerInterface`
and pass the custom serializer to `QueryLocation`.

#### Generic Promise And Structured PHPDoc Types

Guzzle Services command handler stack annotations now use generic
`PromiseInterface<ResultInterface, mixed>` PHPDoc types. This is a
static-analysis-only change and does not alter runtime behavior, but projects
with stricter static analysis may see new or different diagnostics.

Code using unparameterized promise types continues to work. If your project
extends `GuzzleClient`, provides custom command middleware, or documents reusable
command handlers, you may need to update your PHPDoc annotations to include
promise fulfillment and rejection types.

Service client transformer, service description, operation, parameter, and
client config PHPDoc now uses structured array and callable shapes. The PHPDoc
changes are static-analysis-only, but stricter static analysis may now report
invalid option keys, invalid option value types, or callback annotations that
were previously hidden behind loose `array` or `callable` PHPDoc. Invalid
client configuration option values are additionally rejected at runtime; see
the Client Configuration Option Types section.

If your project documents reusable service description, operation, or parameter
schema arrays, update those PHPDoc annotations to match the supported shapes.

Command-to-request transformers are documented as receiving `CommandInterface`.
Response-to-result transformers are documented as receiving `ResponseInterface`,
`RequestInterface`, and `CommandInterface`. Lower-arity userland callables
continue to work at runtime when PHP accepts them.

#### Parameter Presence Checks

`Parameter::has()` now treats explicit `0`, `'0'`, `0.0`, and `false` values as
present. It returns `false` for unset values, `null`, empty strings, and empty
arrays.

If your application calls `Parameter::has()` as a truthiness check, update that
code to fetch the value and compare it explicitly.

#### Service Descriptions and URIs

Guzzle PSR-7 3.x validates URI hosts, URI schemes, query values, and
iterator-backed stream chunks more strictly. Invalid `baseUri` values, operation
URI templates, request hosts, or unsupported query data may now fail earlier.

Guzzle URI Template 2.x now rejects invalid template syntax, invalid UTF-8,
and non-finite float values, expands boolean values as `1` and `0`, and treats
sparse integer-keyed arrays as associative maps. Operation URI templates and
`uri` parameters that relied on 1.x leniency may now throw or expand
differently; review the
[URI Template upgrade guide](https://github.com/guzzle/uri-template/blob/2.0/UPGRADING.md)
before upgrading.

#### Native PHP Serialization of Runtime Objects

`GuzzleClient`, `ValidatedDescriptionHandler`, `Serializer`, and `Deserializer`
no longer support native PHP `serialize()` or `unserialize()`. Persist service
description arrays or configuration instead of runtime pipeline objects.

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
response location implementations should use PSR-7 message interfaces and
return the modified message instead of mutating the message in place.

The old subscriber-based response processing and input validation hooks were
replaced by Guzzle Command handlers. Move custom validation or processing logic
to command handlers when upgrading.
