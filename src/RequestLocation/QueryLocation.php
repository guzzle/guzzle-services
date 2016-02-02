<?php
namespace GuzzleHttp\Command\Guzzle\RequestLocation;

use GuzzleHttp\Command\Guzzle\Parameter;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\CommandInterface;

/**
 * Adds query string values to requests
 * @TODO fix
 */
class QueryLocation extends AbstractLocation
{
    public function visit(
        CommandInterface $command,
        RequestInterface $request,
        Parameter $param
    ) {
        $request->getQuery()[$param->getWireName()] = $this->prepareValue(
            $command[$param->getName()],
            $param
        );

        return $request;
    }

    public function after(
        CommandInterface $command,
        RequestInterface $request,
        Operation $operation
    ) {
        $additional = $operation->getAdditionalParameters();
        if ($additional && $additional->getLocation() == $this->locationName) {
            foreach ($command->toArray() as $key => $value) {
                if (!$operation->hasParam($key)) {
                    $request->getQuery()[$key] = $this->prepareValue(
                        $value,
                        $additional
                    );
                }
            }
        }

        return $request;
    }
}
