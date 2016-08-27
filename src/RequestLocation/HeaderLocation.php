<?php
namespace GuzzleHttp\Command\Guzzle\RequestLocation;

use GuzzleHttp\Command\Guzzle\Parameter;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\CommandInterface;

/**
 * Request header location
 */
class HeaderLocation extends AbstractLocation
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
        $value = $command[$param->getName()];

        return $request->withHeader($param->getWireName(), $param->filter($value));
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
        /** @var Parameter $additional */
        $additional = $operation->getAdditionalParameters();
        if ($additional && ($additional->getLocation() === $this->locationName)) {
            foreach ($command->toArray() as $key => $value) {
                if (!$operation->hasParam($key)) {
                    $request = $request->withHeader($key, $additional->filter($value));
                }
            }
        }

        return $request;
    }
}
