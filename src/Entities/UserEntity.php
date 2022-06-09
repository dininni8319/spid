<?php
/**
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/thephpleague/oauth2-server
 */

namespace Jef\Entities;

use League\OAuth2\Server\Entities\UserEntityInterface;

class UserEntity implements UserEntityInterface
{
    protected string $CF;
    protected string $spid_login_res_id;
    protected string $idp;
    protected array $user_data;
    protected string $insert_datetime;
    protected string $expire_datetime;

    public function __construct(string $CF, string $spid_login_res_id, string $idp, array $user_data, string $insert_datetime,string $expire_datetime)
    {
        $this->CF = $CF;
        $this->spid_login_res_id = $spid_login_res_id;
        $this->idp = $idp;
        $this->user_data = $user_data;
        $this->insert_datetime = $insert_datetime;
        $this->expire_datetime = $expire_datetime;
    }

    /**
     * @return string
     */
    public function getIdp(): string
    {
        return $this->idp;
    }

    /**
     * @param string $idp
     */
    public function setIdp(string $idp): void
    {
        $this->idp = $idp;
    }

    /**
     * @return string
     */
    public function getSpidLoginResId(): string
    {
        return $this->spid_login_res_id;
    }

    /**
     * @param string $spid_login_res_id
     * @return UserEntity
     */
    public function setSpidLoginResId(string $spid_login_res_id): UserEntity
    {
        $this->spid_login_res_id = $spid_login_res_id;
        return $this;
    }


    /**
     * Return the user's identifier.
     *
     * @return mixed
     */
    public function getIdentifier()
    {
        return $this->CF;
    }

    /**
     * @return string
     */
    public function getCF(): string
    {
        return $this->CF;
    }

    /**
     * @param string $CF
     * @return UserEntity
     */
    public function setCF(string $CF): UserEntity
    {
        $this->CF = $CF;
        return $this;
    }

    /**
     * @return array
     */
    public function getUserData(): array
    {
        return $this->user_data;
    }

    /**
     * @param array $user_data
     * @return UserEntity
     */
    public function setUserData(array $user_data): UserEntity
    {
        $this->user_data = $user_data;
        return $this;
    }

    /**
     * @return string
     */
    public function getInsertDatetime(): string
    {
        return $this->insert_datetime;
    }

    /**
     * @param string $insert_datetime
     * @return UserEntity
     */
    public function setInsertDatetime(string $insert_datetime): UserEntity
    {
        $this->insert_datetime = $insert_datetime;
        return $this;
    }

    /**
     * @return string
     */
    public function getExpireDatetime(): string
    {
        return $this->insert_datetime;
    }

    /**
     * @param string $expire_datetime
     * @return UserEntity
     */
    public function setExpireDatetime(string $expire_datetime): UserEntity
    {
        $this->expire_datetime = $expire_datetime;
        return $this;
    }
}
