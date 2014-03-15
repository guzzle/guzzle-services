<?php

namespace GuzzleHttp\Tests\Command\Guzzle;

use GuzzleHttp\Command\Guzzle\Operation;
use GuzzleHttp\Command\Guzzle\Command;
use GuzzleHttp\Command\Guzzle\Description;

abstract class AbstractLocationTest extends \PHPUnit_Framework_TestCase
{
    protected function getCommand(Operation $operation = null)
    {
        return new Command(
            $operation ?: new Operation([], new Description([])),
            ['foo' => 'bar']
        );
    }
}
