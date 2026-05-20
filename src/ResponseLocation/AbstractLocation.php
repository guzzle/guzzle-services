<?php

declare(strict_types=1);

namespace GuzzleHttp\Command\Guzzle\ResponseLocation;

use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\ResultInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class AbstractLocation
 */
abstract class AbstractLocation implements ResponseLocationInterface
{
    protected string $locationName;

    /**
     * Set the name of the location
     */
    public function __construct(string $locationName)
    {
        $this->locationName = $locationName;
    }

    public function before(
        ResultInterface $result,
        ResponseInterface $response,
        Parameter $model
    ): ResultInterface {
        return $result;
    }

    public function after(
        ResultInterface $result,
        ResponseInterface $response,
        Parameter $model
    ): ResultInterface {
        return $result;
    }

    public function visit(
        ResultInterface $result,
        ResponseInterface $response,
        Parameter $param
    ): ResultInterface {
        return $result;
    }
}
