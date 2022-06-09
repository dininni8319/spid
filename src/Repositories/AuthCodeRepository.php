<?php
/**
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace Jef\Repositories;

use Jef\services\DBConnection;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Jef\Entities\AuthCodeEntity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    private DBConnection $db;

    public function __construct()
    {
        $this->db = new DBConnection();
    }

    private string $code_entity_table = 'cnf_spid.code';

    /**
     * {@inheritdoc}
     */
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity)
    {
        // Some logic to persist the auth code to a database
        $type = "auth_code";
        $key = $authCodeEntity->getIdentifier();

        $value = serialize($authCodeEntity);
        $expire_datetime = ((new \DateTime("now"))->modify("+30 minutes"))->format(DBConnection::DATETIME_FORMAT); //Il tempo di vita è di 30 minuti
        //$expire_datetime = date('d-m-Y h:i:s A', strtotime("+30 minutes")); //Il tempo di vita è di 30 minuti
        $is_revoked = "false";

        //La chiave non deve essere null
        if(empty($key)){
            throw new \Exception("The key could not be NULL");
        }
        $query = "INSERT INTO " . $this->code_entity_table . " (`type`, `key`, `value`, `expire_datetime`, `is_revoked`)  VALUES (:type,:key,:value,:expire_datetime,:is_revoked)";
        try {
            $connection = $this->db->get_connection();
            $stmt = $connection->prepare($query);
            $stmt->bindParam(':type', $type, \PDO::PARAM_STR);
            $stmt->bindParam(':key', $key, \PDO::PARAM_STR);
            $stmt->bindParam(':value', $value, \PDO::PARAM_STR);
            $stmt->bindParam(':expire_datetime', $expire_datetime, \PDO::PARAM_STR);
            $stmt->bindParam(':is_revoked', $is_revoked, \PDO::PARAM_STR);
            $stmt->execute();
        } catch (\PDOException $pdo_execp) {
            var_dump($pdo_execp);
            die();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function revokeAuthCode($codeId)
    {

        $query = "UPDATE " . $this->code_entity_table . " SET `is_revoked` = 1 WHERE `key` = :key";
        try {
            $connection = $this->db->get_connection();
            $stmt = $connection->prepare($query);
            $stmt->bindParam(':key', $codeId, \PDO::PARAM_STR);
            $stmt->execute();
        } catch (\PDOException $pdo_execp) {
            var_dump($pdo_execp->getMessage());
        } catch (\Throwable $err) {
            var_dump($err->getMessage());
        }
        // Some logic to revoke the auth code in a database
    }

    /**
     * {@inheritdoc}
     */
    public function isAuthCodeRevoked($codeId)
    {
        $now = (new \DateTime("now"))->format(DBConnection::DATETIME_FORMAT);
        $query = "SELECT * FROM " . $this->code_entity_table . " WHERE `key` = :keyy AND `is_revoked` != 1 AND expire_datetime > now()";

        try {
            $connection = $this->db->get_connection();
            $stmt = $connection->prepare($query);
            $stmt->bindParam(':keyy', $codeId, \PDO::PARAM_STR);

            $stmt->execute();
            $auth_code_res = $stmt->fetch();

            if (!$auth_code_res) {
                //Errore nello statement o il codeId è scaduto
                $this->revokeAuthCode($codeId);
                return true;
            }
            return false;
        } catch (\PDOException $pdo_execp) {
            var_dump($pdo_execp->getMessage());
        } catch (\Throwable $err) {
            var_dump($err->getMessage());
        }
        return false; // The auth code has not been revoked
    }

    /**
     * {@inheritdoc}
     */
    public function getNewAuthCode()
    {
        return new AuthCodeEntity();
    }

    /**
     * Funzione che permette ad un job di aggiornare gli auth code scaduti impostante lo stato di "scaduto" a vero
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param AuthorizationServer $server
     * @return ResponseInterface
     */
    public function revokeExpiredAuthCodes(ServerRequestInterface $request, ResponseInterface $response, AuthorizationServer $server)
    {
        $body = $response->getBody();

        $now = (new \DateTime("now"))->format(DBConnection::DATETIME_FORMAT);
        $query = "UPDATE ".$this->code_entity_table." SET `is_revoked` = 1 WHERE `expire_datetime` > :now";
        try {
            $connection = $this->db->get_connection();
            $stmt = $connection->prepare($query);
            $stmt->bindParam(':now',$now);

            if(!$stmt->execute()){
                $body->write("Auth Code Revoke ERROR");
                return $response->withStatus(500)->withBody($body);
            }
            $body->write("Auth Code Revoke SUCCESS");
            return $response->withStatus(200)->withBody($body);

        }catch (\PDOException $pdo_execp){
            $body->write($pdo_execp->getMessage());
            $body->write("Auth Code Revoke ERROR");
            return $response->withStatus(500)->withBody($body);
        }catch (\Throwable $err){
            $body->write($err->getMessage());
            $body->write("Auth Code Revoke ERROR");
            return $response->withStatus(500)->withBody($body);
        }
    }
}
