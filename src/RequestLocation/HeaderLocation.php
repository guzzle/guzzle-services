<?php

declare(strict_types=1);

namespace GuzzleHttp\Command\Guzzle\RequestLocation;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\Parameter;
use Psr\Http\Message\RequestInterface;

/**
 * Request header location
 */
class HeaderLocation extends AbstractLocation
{
    /**
     * Set the name of the location
     */
    public function __construct(string $locationName = 'header')
    {
        parent::__construct($locationName);
    }

    public function visit(
        CommandInterface $command,
        RequestInterface $request,
        Parameter $param
    ): RequestInterface {
        $value = $command[$param->getName()];

        return $request->withHeader($param->getWireName(), self::prepareHeaderValue($param->filter($value)));
    }

    public function after(
        CommandInterface $command,
        RequestInterface $request,
        Operation $operation
    ): RequestInterface {
        $additional = $operation->getAdditionalParameters();
        if ($additional && ($additional->getLocation() === $this->locationName)) {
            foreach ($command->toArray() as $key => $value) {
                if (!$operation->hasParam($key)) {
                    $request = $request->withHeader($key, self::prepareHeaderValue($additional->filter($value)));
                }
            }
        }

        return $request;
    }

    /**
     * @param mixed $value
     *
     * @return string|string[]
     */
    private static function prepareHeaderValue($value)
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (!is_string($item)) {
                    throw new \InvalidArgumentException('Header location values must be strings or arrays of strings.');
                }
            }

            return $value;
        }

        throw new \InvalidArgumentException('Header location values must be strings or arrays of strings.');
    }
}
