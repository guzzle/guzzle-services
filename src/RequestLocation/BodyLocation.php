<?php

declare(strict_types=1);

namespace GuzzleHttp\Command\Guzzle\RequestLocation;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Guzzle\NonFiniteFloats;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Psr7;
use Psr\Http\Message\RequestInterface;

/**
 * Adds a body to a request
 */
class BodyLocation extends AbstractLocation
{
    /**
     * Set the name of the location
     */
    public function __construct(string $locationName = 'body')
    {
        parent::__construct($locationName);
    }

    public function visit(
        CommandInterface $command,
        RequestInterface $request,
        Parameter $param
    ): RequestInterface {
        $oldValue = $request->getBody()->getContents();

        $value = $command[$param->getName()];
        $filtered = $param->filter($value);
        NonFiniteFloats::assertFinite($filtered, 'a body location value');
        $value = $param->getName().'='.$filtered;

        if ($oldValue !== '') {
            $value = $oldValue.'&'.$value;
        }

        return $request->withBody(Psr7\Utils::streamFor($value));
    }
}
