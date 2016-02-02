<?php
namespace GuzzleHttp\Command\Guzzle\RequestLocation;

use GuzzleHttp\Command\Guzzle\Parameter;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Post\PostBodyInterface;
use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Guzzle\Operation;

/**
 * Adds POST fields to a request
 * @TODO Fix
 */
class PostFieldLocation extends AbstractLocation
{
    public function visit(
        CommandInterface $command,
        RequestInterface $request,
        Parameter $param
    ) {
        $body = $request->getBody();
        if (!($body instanceof PostBodyInterface)) {
            throw new \RuntimeException('Must be a POST body interface');
        }

        $body->setField(
            $param->getWireName(),
            $this->prepareValue($command[$param->getName()], $param)
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

            $body = $request->getBody();
            if (!($body instanceof PostBodyInterface)) {
                throw new \RuntimeException('Must be a POST body interface');
            }

            foreach ($command->toArray() as $key => $value) {
                if (!$operation->hasParam($key)) {
                    $body->setField(
                        $key,
                        $this->prepareValue($value, $additional)
                    );
                }
            }
        }

        return $request;
    }
}
