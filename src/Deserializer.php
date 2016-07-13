<?php
namespace GuzzleHttp\Command\Guzzle;

use GuzzleHttp\Command\Guzzle\ResponseLocation\BodyLocation;
use GuzzleHttp\Command\Guzzle\ResponseLocation\HeaderLocation;
use GuzzleHttp\Command\Guzzle\ResponseLocation\ReasonPhraseLocation;
use GuzzleHttp\Command\Guzzle\ResponseLocation\StatusCodeLocation;
use GuzzleHttp\Command\Guzzle\ResponseLocation\JsonLocation;
use GuzzleHttp\Command\Guzzle\ResponseLocation\XmlLocation;
use GuzzleHttp\Command\Guzzle\ResponseLocation\ResponseLocationInterface;
use GuzzleHttp\Command\Result;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Serializes requests for a given command.
 */
class Deserializer
{
    /** @var ResponseLocationInterface[] */
    private $locations;

    /** @var DescriptionInterface */
    private $description;

    /**
     * @param DescriptionInterface        $description
     * @param ResponseLocationInterface[] $responseLocations Extra response locations
     */
    public function __construct(
        DescriptionInterface $description,
        array $responseLocations = []
    ) {
        static $defaultResponseLocations;
        if (!$defaultResponseLocations) {
            $defaultResponseLocations = [
                'body'      => new BodyLocation('body'),
                'reason'    => new ReasonPhraseLocation('reason'),
                'status'    => new StatusCodeLocation('status'),
                'header'    => new HeaderLocation('header'),
                'json'      => new JsonLocation('json'),
                'xml'       => new XmlLocation('xml'),
            ];
        }

        $this->locations = $responseLocations + $defaultResponseLocations;
        $this->description = $description;
    }

    public function __invoke(ResponseInterface $response, RequestInterface $request = null)
    {
        $result = new Result();
        var_dump($response, $request);die;

        $visitedLocations = [];

        foreach (array_keys($visitedLocations) as $location) {
            $result = $this->locations[$location]->before($result, $response, $param);
        }

        foreach (array_keys($visitedLocations) as $location) {
            $result = $this->locations[$location]->after($result, $response, $param);
        }

        return new Result();
    }
}
