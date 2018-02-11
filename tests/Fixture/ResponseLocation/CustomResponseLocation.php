<?php
namespace GuzzleHttp\Tests\Command\Guzzle\Fixture\ResponseLocation;

use GuzzleHttp\Command\Guzzle\Parameter;
use GuzzleHttp\Command\Guzzle\ResponseLocation\JsonLocation;
use GuzzleHttp\Command\ResultInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class CustomResponseLocationFoo
 *
 * @package GuzzleHttp\Tests\Command\Guzzle\Fixture\ResponseLocation
 */
class CustomResponseLocationFoo
{
    public $foo;
    public $baz;

    public function hydrate(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->$key = $value;
        }
    }
}

/**
 * Class CustomResponseLocation
 *
 * @package GuzzleHttp\Tests\Command\Guzzle\Fixture\ResponseLocation
 */
class CustomResponseLocation extends JsonLocation
{
    public function after(
        ResultInterface $result,
        ResponseInterface $response,
        Parameter $model
    ) {
        $result = parent::after($result, $response, $model);

        $entity = new CustomResponseLocationFoo;
        $entity->hydrate($result->toArray());

        return $entity;
    }
}