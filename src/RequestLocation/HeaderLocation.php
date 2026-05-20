<?php

namespace GuzzleHttp\Command\Guzzle\RequestLocation;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\Parameter;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Request header location
 */
class HeaderLocation extends AbstractLocation
{
    /**
     * Set the name of the location
     *
     * @param string $locationName
     */
    public function __construct($locationName = 'header')
    {
        parent::__construct($locationName);
    }

    /**
     * @return MessageInterface
     */
    public function visit(
        CommandInterface $command,
        RequestInterface $request,
        Parameter $param
    ) {
        $value = $command[$param->getName()];

        return $request->withHeader($param->getWireName(), self::prepareHeaderValue($param->filter($value)));
    }

    /**
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

        if (is_scalar($value)) {
            \trigger_deprecation(
                'guzzlehttp/guzzle-services',
                '1.6',
                'Passing %s as a header location value is deprecated; guzzlehttp/guzzle-services 2.0 requires string|string[].',
                get_debug_type($value)
            );

            return (string) $value;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($item)) {
                    continue;
                }

                if (!is_scalar($item)) {
                    throw new \InvalidArgumentException('Header location values must be scalar or an array of scalars.');
                }

                \trigger_deprecation(
                    'guzzlehttp/guzzle-services',
                    '1.6',
                    'Passing %s inside a header location value array is deprecated; guzzlehttp/guzzle-services 2.0 requires string|string[].',
                    get_debug_type($item)
                );

                $value[$key] = (string) $item;
            }

            return $value;
        }

        throw new \InvalidArgumentException('Header location values must be scalar or an array of scalars.');
    }
}
