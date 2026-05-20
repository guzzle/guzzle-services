<?php

declare(strict_types=1);

namespace GuzzleHttp\Command\Guzzle\QuerySerializer;

class Rfc3986Serializer implements QuerySerializerInterface
{
    private bool $removeNumericIndices;

    public function __construct(bool $removeNumericIndices = false)
    {
        $this->removeNumericIndices = $removeNumericIndices;
    }

    /**
     * {@inheritDoc}
     */
    public function aggregate(array $queryParams): string
    {
        $queryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        if ($this->removeNumericIndices) {
            $queryString = preg_replace('/%5B[0-9]+%5D/simU', '%5B%5D', $queryString);
        }

        return $queryString;
    }
}
