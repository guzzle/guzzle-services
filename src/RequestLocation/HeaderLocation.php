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
        #[\SensitiveParameter]
        CommandInterface $command,
        #[\SensitiveParameter]
        RequestInterface $request,
        Parameter $param
    ): RequestInterface {
        $value = $command[$param->getName()];

        return $request->withHeader($param->getWireName(), self::prepareHeaderValue($param->filter($value)));
    }

    public function after(
        #[\SensitiveParameter]
        CommandInterface $command,
        #[\SensitiveParameter]
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
    private static function prepareHeaderValue(
        #[\SensitiveParameter]
        $value
    ) {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            if ($value === []) {
                throw new \InvalidArgumentException('Header location values must be strings or non-empty arrays of strings.');
            }

            foreach ($value as $item) {
                if (!is_string($item)) {
                    throw new \InvalidArgumentException(\sprintf('Header location value array items must be strings; got %s.', get_debug_type($item)));
                }
            }

            return $value;
        }

        throw new \InvalidArgumentException(\sprintf('Header location values must be strings or non-empty arrays of strings; got %s.', get_debug_type($value)));
    }
}
