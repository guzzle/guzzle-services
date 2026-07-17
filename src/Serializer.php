<?php

declare(strict_types=1);

namespace GuzzleHttp\Command\Guzzle;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Guzzle\RequestLocation\BodyLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\FormParamLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\HeaderLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\JsonLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\MultiPartLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\QueryLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\RequestLocationInterface;
use GuzzleHttp\Command\Guzzle\RequestLocation\XmlLocation;
use GuzzleHttp\Psr7\DiagnosticValue;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\UriTemplate\UriTemplate;
use Psr\Http\Message\RequestInterface;

/**
 * Serializes requests for a given command.
 */
class Serializer
{
    use NonSerializableTrait;

    /** @var RequestLocationInterface[] */
    private array $locations;

    private DescriptionInterface $description;

    /** @var RequestLocationInterface[] */
    private array $customRequestLocations;

    /**
     * @param RequestLocationInterface[] $requestLocations Extra request locations
     */
    public function __construct(
        DescriptionInterface $description,
        array $requestLocations = []
    ) {
        $this->customRequestLocations = $requestLocations;
        $this->resetDefaultRequestLocations();
        $this->description = $description;
    }

    public function __invoke(CommandInterface $command): RequestInterface
    {
        $request = $this->createRequest($command);

        try {
            return $this->prepareRequest($command, $request);
        } catch (\Throwable $e) {
            $this->resetDefaultRequestLocations();

            throw $e;
        }
    }

    private function resetDefaultRequestLocations(): void
    {
        $this->locations = $this->customRequestLocations + [
            'body' => new BodyLocation(),
            'query' => new QueryLocation(),
            'header' => new HeaderLocation(),
            'json' => new JsonLocation(),
            'xml' => new XmlLocation(),
            'formParam' => new FormParamLocation(),
            'multipart' => new MultiPartLocation(),
        ];
    }

    /**
     * Prepares a request for sending using location visitors
     *
     * @param RequestInterface $request Request being created
     *
     * @throws \RuntimeException If a location cannot be handled
     */
    protected function prepareRequest(
        CommandInterface $command,
        RequestInterface $request
    ): RequestInterface {
        $visitedLocations = [];
        $operation = $this->description->getOperation($command->getName());

        // Visit each actual parameter
        foreach ($operation->getParams() as $name => $param) {
            /* @var Parameter */
            $location = $param->getLocation();
            // Skip parameters that have not been set or are URI location
            if ($location == 'uri' || !$command->hasParam($name)) {
                continue;
            }
            if (!isset($this->locations[$location])) {
                throw new \RuntimeException(\sprintf('No location registered for %s', DiagnosticValue::escape((string) $name)));
            }
            $visitedLocations[$location] = true;
            $request = $this->locations[$location]->visit($command, $request, $param);
        }

        // Ensure that the after() method is invoked for additionalParameters
        $additional = $operation->getAdditionalParameters();
        if ($additional) {
            if ($location = $additional->getLocation()) {
                if (!isset($this->locations[$location])) {
                    throw new \RuntimeException('No location registered for additionalParameters');
                }
                $visitedLocations[$location] = true;
            }
        }

        // Call the after() method for each visited location
        foreach (array_keys($visitedLocations) as $location) {
            $request = $this->locations[$location]->after($command, $request, $operation);
        }

        return $request;
    }

    /**
     * Create a request for the command and operation
     *
     * @throws \RuntimeException
     */
    protected function createRequest(CommandInterface $command): RequestInterface
    {
        $operation = $this->description->getOperation($command->getName());

        // If command does not specify a template, assume the client's base URL.
        if (null === $operation->getUri()) {
            return new Request(
                $operation->getHttpMethod(),
                $this->description->getBaseUri()
            );
        }

        return $this->createCommandWithUri($operation, $command);
    }

    /**
     * Create a request for an operation with a uri merged onto a base URI
     *
     * @return Request
     */
    private function createCommandWithUri(
        Operation $operation,
        CommandInterface $command
    ): RequestInterface {
        // Get the path values and use the client config settings
        $variables = [];
        foreach ($operation->getParams() as $name => $arg) {
            /* @var Parameter */
            if ($arg->getLocation() == 'uri') {
                if (isset($command[$name])) {
                    $variables[$name] = $arg->filter($command[$name]);
                    if (!is_array($variables[$name])) {
                        NonFiniteFloats::assertFinite($variables[$name], 'a uri location value');
                        $variables[$name] = (string) $variables[$name];
                    }
                }
            }
        }

        // Expand the URI template.
        $uri = new Uri(UriTemplate::expand((string) $operation->getUri(), $variables));

        return new Request(
            $operation->getHttpMethod(),
            UriResolver::resolve($this->description->getBaseUri(), $uri)
        );
    }
}
