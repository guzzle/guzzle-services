<?php
namespace GuzzleHttp\Command\Guzzle\RequestLocation;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Guzzle\Parameter;
use Psr\Http\Message\RequestInterface;

/**
 * Adds a body to a request
 */
class BodyLocation extends AbstractLocation
{
    public function visit(
        CommandInterface $command,
        RequestInterface $request,
        Parameter $param,
        array $context
    ) {
        $value = $command[$param->getName()];
        $request->setBody(Psr7\stream_for($param->filter($value)));
    }
}
