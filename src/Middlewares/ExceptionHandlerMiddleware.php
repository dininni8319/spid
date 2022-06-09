<?php

namespace Jef\middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Response;

class ExceptionHandlerMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
            /* } catch (ICustomHttpException $httpException) {
            $response = new ExtendedResponse();

            return $response->withStatus($httpException->getCode())->withJson([
                'message' => $httpException->getMessage(),
                'detail' => $httpException->getDetail(),
                'trace' => $httpException->getTrace()
            ]); */
        } catch (HttpNotFoundException $e) {
            $response = new Response();
            $response->getBody()->write(json_encode([
                'message' => 'Not Found Exception',
                'detail' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTrace()
            ]));
            return $response->withStatus(404)->withHeader("Content-Type","application/json");

        }catch (\Throwable $e){
            die(var_dump($e));
            $response = new Response();
            $response->getBody()->write(json_encode([
                'message' => 'Procesing Error',
                'detail' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTrace()
            ]));
            return $response->withStatus(500)->withHeader("Content-Type","application/json");
        }
    }
}
