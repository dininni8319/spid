<?php

namespace Jef\controllers;
require_once (__DIR__."/../../spid-php.php");
use Jef\Repositories\UserRepository;
use Jef\Entities\UserEntity;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;

use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Stream;

class AuthorizeController
{
    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param AuthorizationServer $server
     * @return ResponseInterface
     */
    public function authorize(ServerRequestInterface $request, ResponseInterface $response, AuthorizationServer $server): ResponseInterface
    {

        //SPID VARIABLE
        $params = $request->getQueryParams();
        $idp = '';
        if (!empty($params) && isset($params['idp'])) {
            $idp = '?idp=' . $params['idp'];
        }
        try {
            /* QUESTO é UN WORKAROUND PER RISOLVERE I PROBLEMI DI SESSIONE */
            $spidsdk = new \SPID_PHP(false);
            $authenticated = $spidsdk->isAuthenticated();
            $authRequest = $server->validateAuthorizationRequest($request);
            $_SESSION['auth_request'] = $authRequest; //Salvo la richiesta in sessione
            if ($authenticated) {
                return $response->withStatus(302)->withHeader('Location', '/authenticated' . $idp);
            } else {
                return $response->withStatus(302)->withHeader('Location', '/login' . $idp);
            }

        } catch (OAuthServerException $exception) {
            return $exception->generateHttpResponse($response);
        } catch (\Exception $exception) {
            //$body = new Stream('php://temp', 'r+');
            $body = $response->getBody();
            $body->write($exception->getMessage());

            return $response->withStatus(500)->withBody($body);
        }

    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param AuthorizationServer $server
     * @return ResponseInterface
     * @deprecated
     */
    public function login(ServerRequestInterface $request, ResponseInterface $response, AuthorizationServer $server): ResponseInterface
    {
        
        $production = false;
        $html = "";
        $body = $response->getBody();
        $spidsdk = new \SPID_PHP($production);
        $params = $request->getQueryParams();
        //Controllo che l'utente non sia passato in modo diretto da /login e il client non è quindi autorizzato ad effettuare il login
        if(empty($_SESSION['auth_request'])){
            return $response->withStatus(302)->withHeader('Location', '/login-link');
        }
        //TODO IMPLEMENTARE CONTROLLO SUL CAMPO $_SESSION['auth_request']
        if (
            $spidsdk->isAuthenticated()
            && isset($params['idp'])
            && $spidsdk->isIdP($params['idp'])
        ) {
            return $response->withStatus(302)->withHeader('Location', '/authenticated?idp=' . $params['idp']);
        } else {
            try {
                if (!isset($params['idp'])) {
                    ob_start();
                    echo '<link rel="stylesheet" href="/css/style.css">';
                    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
                    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
                    echo '<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">';
                    echo "<div class='login-spid-wrapper'>";
                    echo "<div class='login-spid-title'>";
                    echo "<h1>Benvenuto nel sistema di accesso con spid del CNF</h1>";
                    echo "</div>";
                    echo "<div class='login-spid-section'>";
                    echo "<p>Lo SPID è il sistema unico di accesso con identità digitale ai servizi online della pubblica amministrazione italiana e dei privati aderenti. Cittadini e imprese possono accedere a tali servizi con un'identità digitale unica che ne permette l'accesso e la fruizione da qualsiasi dispositivo.</p>";
                    echo "</div>";
                    echo "<div class='login-spid-section'>";
                    //Sezione link
                    echo "<div class='login-spid-section-link'>";
                    echo "<a>Maggiori informazioni su spid?</a>";
                    echo "<br>";
                    echo "<a>Non hai spid?</a>";
                    echo "</div>";
                    $spidsdk->insertSPIDButtonCSS();
                    $spidsdk->insertSPIDButton("L");
                    $spidsdk->insertSPIDButtonJS();

                    echo "</div>";
                    echo "<div class='login-spid-section-footer'>";
                    echo '<img src="/imgs/spid-agid-logo-lb.png"></img>';
                    echo "</div>";
                    echo "</div>";
                    $html = ob_get_clean();
                    $body->write($html);
                    return $response->withBody($body);
                } else {
                    $spidsdk->login($params['idp'], 1, "https://spid.audaxpa.it/authenticated");
                    // set AttributeConsumingServiceIndex 2
                    //$spidsdk->login($_GET['idp'], 2, "", 2);
                    $html = ob_get_clean();
                    $body->write($html);
                    return $response->withBody($body);
                }
            } catch (OAuthServerException $exception) {
                return $exception->generateHttpResponse($response);
            } catch (\Exception $exception) {
                //$body = new Stream('php://temp', 'r+');
                $body = $response->getBody();
                $body->write($exception->getMessage());
                return $response->withStatus(500)->withBody($body);
            }
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param AuthorizationServer $server
     * @return ResponseInterface
     */
    public function authenticated(ServerRequestInterface $request, ResponseInterface $response, AuthorizationServer $server): ResponseInterface
    {
        $production = false;
        $body = $response->getBody();
        /** Devo mettere questo prima così si inizializza la sua sessione e fa cose*/
        $spidsdk = new \SPID_PHP($production);
        //Recupero le variabili della richiesta di autorizzazione che avevo salvato inizialmente
        /** @var AuthorizationRequest $authRequest */
        if(empty($_SESSION['auth_request'])) {
            throw new \Exception('empty $_SESSION cannot restore auth_request');
        }
        $authRequest = $_SESSION['auth_request'];
        
        $idp = $spidsdk->getIdP();
        $spid_login_res_id = $spidsdk->getResponseID();
        $user_data = [];
        $user_data['spid_login_res_id'] = $spid_login_res_id;
        $user_data['idp'] = $idp;

        foreach ($spidsdk->getAttributes() as $attribute => $value) {
            $user_data[$attribute] = $value[0];
        }

        //$user_data['auth_request'] = json_encode($authRequest); //Richiesta di auntenticazione
        $user = new UserEntity(
            $user_data['fiscalNumber'],
            $user_data['spid_login_res_id'],
            $user_data['idp'],
            $user_data,
            date('d-m-Y h:i:s A'),
            date('d-m-Y h:i:s A', strtotime("+30 minutes"))
        );
        $authRequest->setUser($user);

        $user_repository = new UserRepository();
        $user_repository->save_user($user); //Salvo l'utente in database
        $user = $user_repository->get_user($user_data['fiscalNumber']); //UserEntity object
        // Once the user has approved or denied the client update the status
        // (true = approved, false = denied)
        $authRequest->setAuthorizationApproved(true);

        // Return the HTTP redirect response
        return $server->completeAuthorizationRequest($authRequest, $response);
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param AuthorizationServer $server
     * @return ResponseInterface
     */
    public function access_token(ServerRequestInterface $request, ResponseInterface $response, AuthorizationServer $server): ResponseInterface
    {
        $access_toke_req_body = $request->getParsedBody();//
        $error = [];
        //$response->getBody()->write(json_encode($access_toke_req_body));
        //return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        //Controllo che le informazioni siano corrette
        if (!isset($access_toke_req_body['code'])) {
            $error = json_encode(array('error' => 'auth_code is required'));
        }
        if (!isset($access_toke_req_body['client_id'])) {
            $error = json_encode(array('error' => 'client_ID is required'));
        }
        if (!isset($access_toke_req_body['client_secret'])) {
            $error = json_encode(array('error' => 'secret is required'));
        }
        if (count($error) > 0) {
            $response->getBody()->write($error);
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            return $server->respondToAccessTokenRequest($request, $response);
        } catch (OAuthServerException $exception) {
            return $exception->generateHttpResponse($response);
        } catch (\Exception $exception) {
            $body = $response->getBody();
            $body->write($exception->getMessage());
            return $response->withStatus(500)->withBody($body);
        }
    }
    public function get_user_data(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $CF =$request->getAttribute('oauth_user_id');
        $body = $response->getBody();

        if(!isset($CF)){
            $response->getBody()->write("ERROR");
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $user = new UserRepository();

        $user_entity = $user->get_user($CF);
        $user_meta = $user_entity->getUserData();

        if(!isset($user_meta) && !isset($user_meta["user_data"])){
            $body->write(json_encode("Errore"));
            return $response->withStatus(500)->withBody($body);
        }
        $body->write(json_encode($user_meta));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json')->withBody($body);
    }

}
