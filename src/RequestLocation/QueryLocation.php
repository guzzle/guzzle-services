<?php
namespace GuzzleHttp\Command\Guzzle\RequestLocation;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Psr7;
use Psr\Http\Message\RequestInterface;

/**
 * Adds query string values to requests
 */
class QueryLocation extends AbstractLocation
{
    /**
     * Set the name of the location
     *
     * @param string $locationName
     */
    public function __construct($locationName = 'query')
    {
        parent::__construct($locationName);
    }

    /**
     * @param CommandInterface $command
     * @param RequestInterface $request
     * @param Parameter        $param
     *
     * @return RequestInterface
     */
    public function visit(
        CommandInterface $command,
        RequestInterface $request,
        Parameter $param
    ) {
        $uri = $request->getUri();
        $query = Psr7\parse_query($uri->getQuery());

        $query[$param->getWireName()] = $this->prepareValue(
            $command[$param->getName()],
            $param
        );

        $uri = $uri->withQuery(http_build_query($query, null, '&', PHP_QUERY_RFC3986));

        return $request->withUri($uri);
    }

    /**
     * @param CommandInterface $command
     * @param RequestInterface $request
     * @param Operation        $operation
     *
     * @return RequestInterface
     */
    public function after(
        CommandInterface $command,
        RequestInterface $request,
        Operation $operation
    ) {
        $additional = $operation->getAdditionalParameters();
        if ($additional && $additional->getLocation() == $this->locationName) {
            foreach ($command->toArray() as $key => $value) {
                if (!$operation->hasParam($key)) {
                    $uri = $request->getUri();
                    $query = Psr7\parse_query($uri->getQuery());

                    $query[$key] = $this->prepareValue(
                        $value,
                        $additional
                    );

                    $uri = $uri->withQuery(http_build_query($query, null, '&', PHP_QUERY_RFC3986));
                    $request = $request->withUri($uri);
                }
            }
        }

        return $request;
    }
}
