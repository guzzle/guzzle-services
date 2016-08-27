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
        $oldValue = $request->getBody()->getContents();

        $value = $command[$param->getName()];
        $value = $param->getName() . '=' . $param->filter($value);

        if ($oldValue !== '') {
            $value = $oldValue . '&' . $value;
        }

        return $request->withBody(Psr7\stream_for($value));
    }
}
