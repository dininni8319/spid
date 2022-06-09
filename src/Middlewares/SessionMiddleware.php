<?php

namespace Jef\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SessionMiddleware implements MiddlewareInterface
{
    /**
     * Invoke middleware.
     *
     * @param ServerRequestInterface $request The request
     * @param RequestHandlerInterface $handler The handler
     *
     * @return ResponseInterface The response
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /*if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start(['name'=>'oauth']);
        }

        $response = $handler->handle($request);

        session_write_close();*/
        $response = $handler->handle($request);
        return $response;
    }
}