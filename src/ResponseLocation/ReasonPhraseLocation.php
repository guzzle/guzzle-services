<?php

declare(strict_types=1);

namespace GuzzleHttp\Command\Guzzle\ResponseLocation;

use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\ResultInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Extracts the reason phrase of a response into a result field
 */
class ReasonPhraseLocation extends AbstractLocation
{
    /**
     * Set the name of the location
     */
    public function __construct(string $locationName = 'reasonPhrase')
    {
        parent::__construct($locationName);
    }

    public function visit(
        ResultInterface $result,
        ResponseInterface $response,
        Parameter $param
    ): ResultInterface {
        $result[$param->getName()] = $param->filter(
            $response->getReasonPhrase()
        );

        return $result;
    }
}
