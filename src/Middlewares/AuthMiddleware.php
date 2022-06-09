<?php
namespace Jef\Middlewares;

use DI\Container;
use League\OAuth2\Server\ResourceServer;
use Psr\Http\Server\MiddlewareInterface;
use Slim\App;
use Laminas\Diactoros\Stream;
use Jef\Entities\UserEntity;
use Jef\services\DBConnection;
use Jef\common\AController;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use Slim\Psr7\Response;
use Slim\Routing\RouteCollectorProxy;
use Slim\Routing\RouteContext;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

class AuthMiddleware implements MiddlewareInterface {
    /**
     * @var ResourceServer
     */
    private $server;

    /**
     * @param ResourceServer $server
     */
    public function __construct(ResourceServer $server)
    {
        $this->server = $server;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // TODO: Implement process() method.
        try {
            $request = $this->server->validateAuthenticatedRequest($request);
            return $handler->handle($request);
        } catch (OAuthServerException $exception) {
            return $exception->generateHttpResponse(new Response());
            // @codeCoverageIgnoreStart
        } catch (\Exception $exception) {
            return (new OAuthServerException($exception->getMessage(), 0, 'unknown_error', 500))
                ->generateHttpResponse(new Response());
            // @codeCoverageIgnoreEnd
        }
    }
}