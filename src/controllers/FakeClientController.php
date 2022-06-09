<?php
namespace Jef\controllers;

use DI\Container;
use Slim\App;
use Laminas\Diactoros\Stream;
use Jef\Entities\UserEntity;
use Jef\services\DBConnection;
use Jef\common\AController;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use Slim\Routing\RouteCollectorProxy;
use Slim\Routing\RouteContext;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;

class FakeClientController {
    //private DBConnection $connection;
    public function __construct(DBConnection $connection)
    {
        $this->connection = $connection;
    }

    //&response_type=code&code_challenge=ZDQ2OGJlY2QzNzRhMGU5ZjhkZjQ3NmI1YmQxYzZkNDZjYjUzZTA3MzRjYjYxMGYwNjc1MzY5MDgxZTkxNmQxZQ&code_challenge_method=S256
    //&state=ZDQ2OGJlY2QzNzRhMGU5ZjhkZjQ3NmI1YmQxYzZkNDZjYjUzZTA3MzRjYjYxMGYwNjc1MzY5MDgxZTkxNmQxZQ


    public function page(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {

        $body = $response->getBody();
        $body->write("<a href='https://auth.devcnf.it/authorize?client_id=mytestapp&redirect_uri=https://auth.devcnf.it/fake/success&scope=basic&response_type=code'>Login with CNF Auth</a>");
        return $response->withBody($body);
    }

    public function success(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $target_url = 'https://auth.devcnf.it/access_token';
        $params = $request->getQueryParams();
        $auth_code = $params['code'];
        $post = [
            'grant_type'=>'authorization_code',
            'code' => urldecode($auth_code), //Necessario se no và in errore
            'client_id' => 'mytestapp',
            "client_secret" => "gp69btKCXeGHENKX",
            'redirect_uri' => 'https://auth.devcnf.it/fake/success'
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$target_url);
        curl_setopt($ch, CURLOPT_POST,1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);

        $res = curl_exec($ch);
        curl_close($ch);

        $res_decoded = json_decode($res);


        $error = $res_decoded->error;
        $access_token = $res_decoded->access_token;


        //Controllo se l'auth server mi ha mandato un errore oppure se non ho l'access token
        if(isset($error) || !isset($access_token)){
            return $response->withHeader('Location', '/login-link')->withStatus(302);
        }

        $user_data = $this->get_user_data($access_token);

        $response->getBody()->write(json_encode($user_data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    function get_user_data($access_token){
        $target_url = 'https://auth.devcnf.it/get_user_data';

        $ch = curl_init();
        $authorization = "Authorization: Bearer ".$access_token; // Prepare the authorisation token
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization )); // Inject the token into the header
        curl_setopt($ch, CURLOPT_URL,$target_url);
        curl_setopt($ch, CURLOPT_POST,1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);

        $res = curl_exec($ch);

        curl_close($ch);

        $server_output = json_decode($res,true);

        curl_close ($ch);
        return $server_output;

    }
}