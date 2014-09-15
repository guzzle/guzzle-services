<?php

namespace GuzzleHttp\Command\Guzzle;

use GuzzleHttp\Command\ServiceClientInterface;

/**
 * Guzzle web service client
 */
interface GuzzleClientInterface extends ServiceClientInterface
{
    /**
     * Returns the service description used by the client
     *
     * @return DescriptionInterface
     */
    public function getDescription();
}
