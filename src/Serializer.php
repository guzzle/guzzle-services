<?php
namespace GuzzleHttp\Command\Guzzle;

use GuzzleHttp\Command\ServiceClientInterface;
use GuzzleHttp\Command\CommandInterface;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Command\Guzzle\RequestLocation\BodyLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\HeaderLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\JsonLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\PostFieldLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\PostFileLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\QueryLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\XmlLocation;
use GuzzleHttp\Command\Guzzle\RequestLocation\RequestLocationInterface;

/**
 * Serializes requests for a given command.
 */
class Serializer
{
    /** @var RequestLocationInterface[] */
    private $requestLocations;

    /** @var DescriptionInterface */
    private $description;

    /**
     * @param DescriptionInterface       $description
     * @param RequestLocationInterface[] $requestLocations Extra request locations
     */
    public function __construct(
        DescriptionInterface $description,
        array $requestLocations = []
    ) {
        static $defaultRequestLocations;
        if (!$defaultRequestLocations) {
            $defaultRequestLocations = [
                'body'      => new BodyLocation('body'),
                'query'     => new QueryLocation('query'),
                'header'    => new HeaderLocation('header'),
                'json'      => new JsonLocation('json'),
                'xml'       => new XmlLocation('xml'),
                'postField' => new PostFieldLocation('postField'),
                'postFile'  => new PostFileLocation('postFile')
            ];
        }

        $this->requestLocations = $requestLocations + $defaultRequestLocations;
        $this->description = $description;
    }

    public function __invoke(CommandInterface $command)
    {
        $request = $this->createRequest($command);
        $this->prepareRequest($command, $request);

        return $request;
    }

    /**
     * Prepares a request for sending using location visitors
     *
     * @param CommandInterface $command
     * @param RequestInterface       $request Request being created
     * @throws \RuntimeException If a location cannot be handled
     */
    protected function prepareRequest(
        CommandInterface $command,
        RequestInterface $request
    ) {
        $visitedLocations = [];
        $context = ['command' => $command];
        $operation = $this->description->getOperation($command->getName());

        // Visit each actual parameter
        foreach ($operation->getParams() as $name => $param) {
            /* @var Parameter $param */
            $location = $param->getLocation();
            // Skip parameters that have not been set or are URI location
            if ($location == 'uri' || !$command->hasParam($name)) {
                continue;
            }
            if (!isset($this->requestLocations[$location])) {
                throw new \RuntimeException("No location registered for $name");
            }
            $visitedLocations[$location] = true;
            $this->requestLocations[$location]->visit(
                $command,
                $request,
                $param,
                $context
            );
        }

        // Ensure that the after() method is invoked for additionalParameters
        if ($additional = $operation->getAdditionalParameters()) {
            $visitedLocations[$additional->getLocation()] = true;
        }

        // Call the after() method for each visited location
        foreach (array_keys($visitedLocations) as $location) {
            $this->requestLocations[$location]->after(
                $command,
                $request,
                $operation,
                $context
            );
        }
    }

    /**
     * Create a request for the command and operation
     *
     * @param CommandTransaction $trans
     *
     * @return RequestInterface
     * @throws \RuntimeException
     * @TODO Fix
     */
    protected function createRequest(CommandTransaction $trans)
    {
        $operation = $this->description->getOperation($trans->command->getName());

        // If the command does not specify a template, then assume the base URL
        // of the client
        if (null === ($uri = $operation->getUri())) {
            return $trans->client->createRequest(
                $operation->getHttpMethod(),
                $this->description->getBaseUrl(),
                $trans->command['request_options'] ?: []
            );
        }

        return $this->createCommandWithUri(
            $operation, $trans->command, $trans->serviceClient
        );
    }

    /**
     * Create a request for an operation with a uri merged onto a base URI
     */
    private function createCommandWithUri(
        Operation $operation,
        CommandInterface $command,
        ServiceClientInterface $client
    ) {
        // Get the path values and use the client config settings
        $variables = [];
        foreach ($operation->getParams() as $name => $arg) {
            /* @var Parameter $arg */
            if ($arg->getLocation() == 'uri') {
                if (isset($command[$name])) {
                    $variables[$name] = $arg->filter($command[$name]);
                    if (!is_array($variables[$name])) {
                        $variables[$name] = (string) $variables[$name];
                    }
                }
            }
        }

        // Expand the URI template.
        $uri = \GuzzleHttp\uri_template($operation->getUri(), $variables);

        // @TODO fix
        return $client->getHttpClient()->createRequest(
            $operation->getHttpMethod(),
            $this->description->getBaseUrl()->combine($uri),
            $command['request_options'] ?: []
        );
    }
}
