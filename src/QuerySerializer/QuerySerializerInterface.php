<?php

declare(strict_types=1);

namespace GuzzleHttp\Command\Guzzle\QuerySerializer;

interface QuerySerializerInterface
{
    /**
     * Aggregate query params and transform them into a string
     */
    public function aggregate(array $queryParams): string;
}
