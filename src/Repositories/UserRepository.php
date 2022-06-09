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
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Jef\Entities\UserEntity;

class UserRepository implements UserRepositoryInterface
{
    private string $user_entity_table = 'cnf_spid.spid_users';
    private DBConnection $db;

    public function __construct(){
        $this->db = new DBConnection();
    }
    /**
     * {@inheritdoc}
     */
    public function getUserEntityByUserCredentials(
        $username,
        $password,
        $grantType,
        ClientEntityInterface $clientEntity
    ) {
        return;
    }
    /**
     * Funzione che salva un utente che ha effettuato il login con SPID nel database
     * @param $cf string codice fiscale dell'utente
     * @param $user_data array informazioni dell'utente che ci manda spid
     * @param $idp string identificativo dell'identity provider con cui l'utente ha effettuato l'accesso
     */
    public function save_user(UserEntity $user){
        //Getting query binding prams
        $CF = $user->getCF();

        $this->prevent_duplicates($CF);
        $spid_login_res_id = $user->getSpidLoginResId();
        $idp = $user->getIdp();
        $user_data = json_encode($user->getUserData()); //user_data viene serializzato in quanto array
        $insert_datetime = $user->getInsertDatetime();
        $expire_datetime = $user->getExpireDatetime();


        $query = "REPLACE INTO ".$this->user_entity_table." (spid_login_res_id,CF,idp,user_data,insert_datetime,expire_datetime) VALUES (:spid_login_res_id,:CF,:idp,:user_data,:insert_datetime,:expire_datetime)";

        try {
            $connection = $this->db->get_connection();

            $stmt = $connection->prepare($query);
            $stmt->bindParam(':spid_login_res_id', $spid_login_res_id,\PDO::PARAM_STR);
            $stmt->bindParam(':CF', $CF,\PDO::PARAM_STR);
            $stmt->bindParam(':idp', $idp,\PDO::PARAM_STR);
            $stmt->bindParam(':user_data', $user_data,\PDO::PARAM_STR);
            $stmt->bindParam(':insert_datetime', $insert_datetime,\PDO::PARAM_STR);
            $stmt->bindParam(':expire_datetime', $expire_datetime,\PDO::PARAM_STR);

            $stmt->execute();
        }catch (\PDOException $e) {
            var_dump($e);
            if($e->getCode() === '23000'){
                return $this->get_user($CF);
            }
           die();
        }catch (\Throwable $err){
            var_dump($err);
            die();
        }
    }
    /**
     * Funzione che ottiene dal database le informazioni dell'utente
     * @param $CF string codice fiscale dell'utente
     * @return UserEntity
     */
    public function get_user(string $CF): UserEntity{
        $query = "SELECT * FROM cnf_spid.spid_users WHERE `CF` = :CF";
        try {
            $connection = $this->db->get_connection();
            $stmt = $connection->prepare($query);
            $stmt->bindParam(":CF",$CF,\PDO::PARAM_STR);
            $stmt->execute();
            $user_data = $stmt->fetch();
            //string $CF, string $spid_login_res_id, string $idp, array $user_data, string $insert_datetime,string $expire_datetime
            $user_entity = new UserEntity($user_data['CF'],$user_data['spid_login_res_id'],$user_data['idp'],json_decode($user_data['user_data'],true),$user_data['insert_datetime'],$user_data['expire_datetime']);
            return $user_entity;
        }catch (\Throwable $err){
            var_dump($err);
        }
    }

    /**
     * Funzione che cancella le informazioni di un utente dal database quanso la sessione scade
     * La sessione scade periodicamente dopo un certo lasso di tempo (es. mezz'ora, un'ora)
     * @return void
     */
    protected function forget_user(string $CF){
        //Prevengo che venga inserito un utente il cui codice fiscale è già stato salvato

        $query = "DELETE FROM ".$this->user_entity_table." WHERE CF = :CF";
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':CF',$CF,\PDO::PARAM_STR);
            $stmt->execute();
        }catch (\PDOException $e) {
            var_dump($e->__toString());
        }catch (\Throwable $err){
            var_dump($err);
        }

    }
    /**
     * Funzione che previene l'insert duplicato di un record
     *
     */
    private function prevent_duplicates($CF): void{
    }
}
