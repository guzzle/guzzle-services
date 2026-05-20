<?php

declare(strict_types=1);

namespace GuzzleHttp\Command\Guzzle;

use GuzzleHttp\Psr7\Uri;

interface DescriptionInterface
{
    /**
     * Get the basePath/baseUri of the description
     */
    public function getBaseUri(): Uri;

    /**
     * Get the API operations of the service
     *
     * @return Operation[] Returns an array of {@see Operation} objects
     */
    public function getOperations(): array;

    /**
     * Check if the service has an operation by name
     *
     * @param string $name Name of the operation to check
     */
    public function hasOperation(string $name): bool;

    /**
     * Get an API operation by name
     *
     * @param string $name Name of the command
     *
     * @throws \InvalidArgumentException if the operation is not found
     */
    public function getOperation(string $name): Operation;

    /**
     * Get a shared definition structure.
     *
     * @param string $id ID/name of the model to retrieve
     *
     * @throws \InvalidArgumentException if the model is not found
     */
    public function getModel(string $id): Parameter;

    /**
     * Get all models of the service description.
     */
    public function getModels(): array;

    /**
     * Check if the service description has a model by name.
     *
     * @param string $id Name/ID of the model to check
     */
    public function hasModel(string $id): bool;

    /**
     * Get the API version of the service
     */
    public function getApiVersion(): ?string;

    /**
     * Get the name of the API
     */
    public function getName(): ?string;

    /**
     * Get a summary of the purpose of the API
     */
    public function getDescription(): ?string;

    /**
     * Format a parameter using named formats.
     *
     * @param string $format Format to convert it to
     * @param mixed  $input  Input string
     *
     * @return mixed
     */
    public function format(string $format, $input);

    /**
     * Get arbitrary data from the service description that is not part of the
     * Guzzle service description specification.
     *
     * @param string $key Data key to retrieve or null to retrieve all extra
     *
     * @return mixed|null
     */
    public function getData(?string $key = null);
}
