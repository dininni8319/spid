<?php

use Jef\controllers\AuthorizeController;
use Jef\Middlewares\ExceptionHandlerMiddleware;
use Jef\Repositories\AuthCodeRepository;
use Jef\Repositories\ClientRepository;
use Jef\Repositories\ScopeRepository;
use Jef\Repositories\AccessTokenRepository;
use Jef\Repositories\RefreshTokenRepository;
use DI\Bridge\Slim\Bridge;
use DI\Container;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Middleware\ResourceServerMiddleware;
use League\OAuth2\Server\ResourceServer;
use Dotenv\Dotenv;
use Jef\controllers\FakeClientController;

require_once '../vendor/autoload.php';
if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . '.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    /** Had to load unsafe to make AWS SDK load env variables properly */
    //$dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__);
    $dotenv->load();
    $dotenv->required(['MYSQL_HOST', 'MYSQL_USER', 'MYSQL_PASS']);
}
$container = new Container();
$container->set(
    AuthorizationServer::class,
    function (
        ClientRepository       $clientRepository,
        AccessTokenRepository  $accessTokenRepository,
        ScopeRepository        $scopeRepository,
        AuthCodeRepository     $authCodeRepository,
        RefreshTokenRepository $refreshTokenRepository
    ): AuthorizationServer {
        $privateKeyPath = __DIR__ . '/private.key';
        $server = new AuthorizationServer(
            $clientRepository,
            $accessTokenRepository,
            $scopeRepository,
            $privateKeyPath,
            'lxZFUEsBCJ2Yb14IF2ygAHI5N4+ZAUXXaSeeJm6+twsUmIen'
        );
        // Enable the authentication code grant on the server with a token TTL of 1 hour
        $server->enableGrantType(
            new AuthCodeGrant(
                $authCodeRepository,
                $refreshTokenRepository,
                new \DateInterval('PT10M')
            ),
            new \DateInterval('PT1H')
        );

        return $server;
    }
);



$container->set(
    ResourceServer::class ,
    function (AccessTokenRepository $accessTokenRepository) : ResourceServer {
        $resource_server  = new \League\OAuth2\Server\ResourceServer($accessTokenRepository,__DIR__ . '/public.key');
        return $resource_server;
    });
$app = Bridge::create($container);
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();
$app->add($app->getContainer()->get(ExceptionHandlerMiddleware::class));
//$app->add(new \League\OAuth2\Server\Middleware\ResourceServerMiddleware($app->getContainer()->get(ResourceServer::class)));
$app->get('/login-link', FakeClientController::class . ':page');
$app->get('/fake/success', FakeClientController::class . ':success');
$app->get('/authorize', AuthorizeController::class . ':authorize');
$app->get('/login', AuthorizeController::class . ':login');
$app->get('/authenticated', AuthorizeController::class . ':authenticated');
$app->post('/access_token', AuthorizeController::class . ':access_token');


$app->post('/get_user_data', AuthorizeController::class . ':get_user_data')->add(new \Jef\Middlewares\AuthMiddleware($app->getContainer()->get(ResourceServer::class)));
//$app->post('/get_user_data', AuthMiddleware::class . ':get_user_data');


// CRON JOB
$app->get('/revoke_auth_codes', AuthCodeRepository::class . ':revokeExpiredAuthCodes');
$app->addErrorMiddleware(true, true, true);
$app->run();
