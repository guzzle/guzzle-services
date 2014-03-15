===============
Guzzle Services
===============

Provides an implementation of the Guzzle Command library that uses Guzzle service descriptions to describe web services, serialize requests, and parse responses into easy to use model structures.

.. code-block:: php

    use GuzzleHttp\Client;
    use GuzzleHttp\Command\Guzzle\GuzzleClient;
    use GuzzleHttp\Command\Guzzle\Description;

    $client = new Client();
    $description = new Description([
        'baseUrl' => 'http://httpbin.org/',
        'operations' => [
            'testing' => [
                'httpMethod' => 'GET',
                'uri' => '/get',
                'responseModel' => 'getResponse',
                'parameters' => [
                    'foo' => [
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

More documentation coming soon.
