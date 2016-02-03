<?php
namespace GuzzleHttp\Command\Guzzle\ResponseLocation;

use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\ResultInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Extracts the reason phrase of a response into a result field
 */
class ReasonPhraseLocation extends AbstractLocation
{
    public function visit(
        ResultInterface $result,
        ResponseInterface $response,
        Parameter $param
    ) {
        $result[$param->getName()] = $param->filter(
            $response->getReasonPhrase()
        );

        return $result;
    }
}
