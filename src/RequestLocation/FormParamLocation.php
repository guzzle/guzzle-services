<?php

declare(strict_types=1);

namespace GuzzleHttp\Command\Guzzle\RequestLocation;

use GuzzleHttp\Command\CommandInterface;
use GuzzleHttp\Command\Guzzle\NonFiniteFloats;
use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Psr7;
use Psr\Http\Message\RequestInterface;

/**
 * Add form_params to a request
 */
class FormParamLocation extends AbstractLocation
{
    protected string $contentType = 'application/x-www-form-urlencoded; charset=utf-8';

    protected array $formParamsData = [];

    /**
     * Set the name of the location
     */
    public function __construct(string $locationName = 'formParam')
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
        $this->formParamsData['form_params'][$param->getWireName()] = $this->prepareValue(
            $command[$param->getName()],
            $param
        );

        return $request;
    }

    public function after(
        #[\SensitiveParameter]
        CommandInterface $command,
        #[\SensitiveParameter]
        RequestInterface $request,
        Operation $operation
    ): RequestInterface {
        $data = $this->formParamsData;
        $this->formParamsData = [];
        $modify = [];

        // Add additional parameters to the form_params array
        $additional = $operation->getAdditionalParameters();
        if ($additional && $additional->getLocation() == $this->locationName) {
            foreach ($command->toArray() as $key => $value) {
                if (!$operation->hasParam($key)) {
                    $data['form_params'][$key] = $this->prepareValue($value, $additional);
                }
            }
        }

        NonFiniteFloats::assertAllFinite($data['form_params'], 'a formParam location value');
        $body = http_build_query($data['form_params'], '', '&');
        $modify['body'] = Psr7\Utils::streamFor($body);
        $modify['set_headers']['Content-Type'] = $this->contentType;

        return Psr7\Utils::modifyRequest($request, $modify);
    }
}
