<?php
/**
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace Jef\Repositories;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Jef\Entities\ClientEntity;

class ClientRepository implements ClientRepositoryInterface
{

    private const CLIENTS = array(
        'cnf-spid-app' => array(
            "CLIENT_NAME"=>'My Awesome App',
            "REDIRECT_URI"=>'https://localhost:44349/ResponseFromSpid.aspx',
            "SECRET" => '$2y$10$lEHgABqqxpAB.nmKQMyz1uT//beneoC6JwSpLn1sBOniRuOsZ9A8.'

        ),
        'mytestapp' => array(
            "CLIENT_NAME"=>'My Test App',
            "REDIRECT_URI"=>'https://auth.devcnf.it/fake/success',
            "SECRET" => '$2y$10$A4R41ZsQezWs0iWCP8KxpuZ8gilCUUXNQ9TgHJDaKsiei4NXGPGfu'
        )
    );
    /**
     * {@inheritdoc}
     */
    public function getClientEntity($clientIdentifier)
    {
        //if(isset(self::CLIENTS[$clientIdentifier])){
            $client = new ClientEntity();
            $client->setIdentifier($clientIdentifier);
            $client->setName(self::CLIENTS[$clientIdentifier]["CLIENT_NAME"]);
            $client->setRedirectUri(self::CLIENTS[$clientIdentifier]["REDIRECT_URI"]);
            $client->setConfidential();

            return $client;
        //}
        //die(func_get_arg());

        throw new \Exception("CLIENT NOT FOUND FIX ME");
    }

    /**
     * {@inheritdoc}
     */
    public function validateClient($clientIdentifier, $clientSecret, $grantType)
    {
        //die("aa");

        $clients = [
            'cnf-spid-app' => [
                'secret'          => \password_hash(self::CLIENTS[$clientIdentifier]["SECRET"], PASSWORD_BCRYPT),
                'name'            => self::CLIENTS[$clientIdentifier]["CLIENT_NAME"],
                'redirect_uri'    => self::CLIENTS[$clientIdentifier]["REDIRECT_URI"],
                'is_confidential' => true,
            ],
        ];

        $client = $this->getClientEntity($clientIdentifier);
        if($client->isConfidential() && \password_verify($clientSecret, self::CLIENTS[$clientIdentifier]['SECRET']) === false){
            return false;
        }
        return true;


        // Check if client is registered
        if (\array_key_exists($clientIdentifier, $clients) === false) {
            return false;
        }

        if (
            $clients[$clientIdentifier]['is_confidential'] === true
            && \password_verify($clientSecret, $clients[$clientIdentifier]['secret']) === false
        ) {
            return false;
        }

        return true;
    }
}
