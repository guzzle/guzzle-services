<?php

declare(strict_types=1);

namespace GuzzleHttp\Command\Guzzle\RequestLocation;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\QuerySerializer\QuerySerializerInterface;
use GuzzleHttp\Command\Guzzle\QuerySerializer\Rfc3986Serializer;
use GuzzleHttp\Psr7;
use Psr\Http\Message\RequestInterface;

/**
 * Adds query string values to requests
 */
class QueryLocation extends AbstractLocation
{
    private QuerySerializerInterface $querySerializer;

    /**
     * Set the name of the location
     */
    public function __construct(string $locationName = 'query', ?QuerySerializerInterface $querySerializer = null)
    {
        parent::__construct($locationName);

        $this->querySerializer = $querySerializer ?: new Rfc3986Serializer();
    }

    public function visit(
        CommandInterface $command,
        RequestInterface $request,
        Parameter $param
    ): RequestInterface {
        $uri = $request->getUri();
        $query = Psr7\Query::parse($uri->getQuery());

        $query[$param->getWireName()] = $this->prepareValue(
            $command[$param->getName()],
            $param
        );

        $uri = $uri->withQuery($this->querySerializer->aggregate($query));

        return $request->withUri($uri);
    }

    public function after(
        CommandInterface $command,
        RequestInterface $request,
        Operation $operation
    ): RequestInterface {
        $additional = $operation->getAdditionalParameters();
        if ($additional && $additional->getLocation() == $this->locationName) {
            foreach ($command->toArray() as $key => $value) {
                if (!$operation->hasParam($key)) {
                    $uri = $request->getUri();
                    $query = Psr7\Query::parse($uri->getQuery());

                    $query[$key] = $this->prepareValue(
                        $value,
                        $additional
                    );

                    $uri = $uri->withQuery($this->querySerializer->aggregate($query));
                    $request = $request->withUri($uri);
                }
            }
        }

        return $request;
    }
}
