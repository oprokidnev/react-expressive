<?php

use React\EventLoop\Factory;
use React\Http\Server as HttpServer;
use React\Socket\Server as Socket;
use React2Psr7\ReactRequestHandler;
use Zend\Expressive\Application;

require_once 'vendor/autoload.php';

$loop = Factory::create();

$socket = new \React\Socket\Server(8033, $loop);

echo 'http://localhost:8033' . PHP_EOL;

/** @var \Interop\Container\ContainerInterface $container */
$container = require 'config/container.php';

/** @var \Zend\Expressive\Application $app */
$app = $container->get(\Zend\Expressive\Application::class);
/* @var $app \Zend\Expressive\Application */

require 'config/pipeline.php';
require 'config/routes.php';

$handledRequests = 0;

$http = new HttpServer($socket, function (\Psr\Http\Message\RequestInterface $request) use ($app, &$handledRequests) {

    try {
        $request = new \Zend\Diactoros\ServerRequest($_SERVER, [], $request->getUri(), $request->getMethod(), $request->getBody(), $request->getHeaders(), $request->getHeader('cookie'), \RingCentral\Psr7\parse_query($request->getUri()->getQuery(), true), $request->getBody());
        $delegate = $app->getDefaultDelegate();
        $response = $app->process($request, $delegate);

        $reactResponse = new React\Http\Response(
            $response->getStatusCode(), $response->getHeaders(), $response->getBody()->getContents()
        );
    } catch (\Throwable $ex) {
        $reactResponse = new React\Http\Response(
            500, [
            'content-type' => 'text/plain'
            ], 'Error 500 occurred'
        );
    }
    $handledRequests ++;
    return $reactResponse;
});

$loop->addPeriodicTimer(20, function () use (&$handledRequests) {
    gc_collect_cycles();
    $kmem = round(memory_get_usage(true) / 1024, 2) ;
    $mem = round(memory_get_usage() / 1024,2);
    echo date('[d.m.Y H:i:s]') . PHP_EOL;
    echo "Requests: $handledRequests\n";
    echo "Memory: $mem KiB\n";
    echo "Real Memory: $kmem KiB\n";
});
$loop->run();
