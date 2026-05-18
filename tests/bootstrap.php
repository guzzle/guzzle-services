<?php

declare(strict_types=1);

use GuzzleHttp\Server\Server;

require __DIR__.'/../vendor/autoload.php';

$port = getenv('GUZZLE_SERVICES_TEST_SERVER_PORT');
if ($port !== false && $port !== '') {
    Server::$port = (int) $port;
    Server::$url = 'http://127.0.0.1:'.Server::$port.'/';
}

Server::start();

register_shutdown_function(static function (): void {
    try {
        Server::stop();
    } catch (Exception $e) {
        // The process may already have been stopped by a local developer.
    }
});
