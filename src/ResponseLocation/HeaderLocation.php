<?php
namespace GuzzleHttp\Command\Guzzle\ResponseLocation;

use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\ResultInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Extracts headers from the response into a result fields
 */
class HeaderLocation extends AbstractLocation
{
    public function visit(
        ResultInterface $result,
        ResponseInterface $response,
        Parameter $param
    ) {
        // Retrieving a single header by name
        $name = $param->getName();
        if ($header = $response->getHeader($param->getWireName())) {
            $result[$name] = $param->filter($header);
        }

        return $result;
    }
}
