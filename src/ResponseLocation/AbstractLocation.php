<?php
namespace GuzzleHttp\Command\Guzzle\ResponseLocation;

use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\ResultInterface;
use Psr\Http\Message\ResponseInterface;

abstract class AbstractLocation implements ResponseLocationInterface
{
    /** @var string */
    protected $locationName;

    /**
     * Set the name of the location
     *
     * @param $locationName
     */
    public function __construct($locationName)
    {
        $this->locationName = $locationName;
    }

    public function before(
        ResultInterface $result,
        ResponseInterface $response,
        Parameter $model
    ) {
        return $result;
    }

    public function after(
        ResultInterface $result,
        ResponseInterface $response,
        Parameter $model
    ) {
        return $result;
    }

    public function visit(
        ResultInterface $result,
        ResponseInterface $response,
        Parameter $param
    ) {
        return $result;
    }
}
