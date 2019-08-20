<?php
namespace GuzzleHttp\Command\Guzzle\RequestLocation;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Psr7;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Adds a body to a request
 */
class BodyLocation extends AbstractLocation
{

    /**
     * Set the name of the location
     *
     * @param string $locationName
     */
    public function __construct($locationName = 'body')
    {
        parent::__construct($locationName);
    }

    /**
     * @param CommandInterface $command
     * @param RequestInterface $request
     * @param Parameter        $param
     *
     * @return MessageInterface
     */
    public function visit(
        CommandInterface $command,
        RequestInterface $request,
        Parameter $param
    ) {
        $existingResponse = $request->getBody()->getContents();

        $value = $command[$param->getName()];
        $filteredValue =
            $param->filter($value, Parameter::FILTER_STAGE_REQUEST_WIRE);

        $valueForResponse = sprintf('%s=%s', $param->getName(), $filteredValue);

        if ($existingResponse == '') {
            $response = $valueForResponse;
        } else {
            $response = $existingResponse . '&' . $valueForResponse;
        }

        return $request->withBody(Psr7\stream_for($response));
    }
}
