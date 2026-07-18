<?php

declare(strict_types=1);

namespace GuzzleHttp\Command\Guzzle\RequestLocation;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Psr7;
use Psr\Http\Message\RequestInterface;

/**
 * Adds POST files to a request
 */
class MultiPartLocation extends AbstractLocation
{
    protected string $contentType = 'multipart/form-data; boundary=';

    protected array $multipartData = [];

    /**
     * Set the name of the location
     */
    public function __construct(string $locationName = 'multipart')
    {
        parent::__construct($locationName);
    }

    public function visit(
        #[\SensitiveParameter]
        CommandInterface $command,
        #[\SensitiveParameter]
        RequestInterface $request,
        Parameter $param
    ): RequestInterface {
        $this->multipartData[] = [
            'name' => $param->getWireName(),
            'contents' => $this->prepareValue($command[$param->getName()], $param),
        ];

        return $request;
    }

    public function after(
        #[\SensitiveParameter]
        CommandInterface $command,
        #[\SensitiveParameter]
        RequestInterface $request,
        Operation $operation
    ): RequestInterface {
        $data = $this->multipartData;
        $this->multipartData = [];
        $modify = [];

        $body = new Psr7\MultipartStream($data);
        $modify['body'] = Psr7\Utils::streamFor($body);
        $request = Psr7\Utils::modifyRequest($request, $modify);
        if ($request->getBody() instanceof Psr7\MultipartStream && !$request->hasHeader('Content-Type')) {
            // Use a multipart/form-data POST if a Content-Type is not set.
            $request = $request->withHeader('Content-Type', $this->contentType.$request->getBody()->getBoundary());
        }

        return $request;
    }
}
