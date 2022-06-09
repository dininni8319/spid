<?php

namespace Jef\services;

class DBConnection
{

    private \PDO $connection;
    const DATETIME_FORMAT = 'Y-m-d H:i:s';
    function __construct()
    {
    }

    private function create_connection(): void
    {
        try {
            $this->MYSQL_HOST = $_ENV['MYSQL_HOST']; // dynamic assignment
            $this->MYSQL_USER = $_ENV['MYSQL_USER']; // dynamic assignment
            $this->MYSQL_PASS = $_ENV['MYSQL_PASS']; // dynamic assignment
            $this->MYSQL_DBNAME = $_ENV['MYSQL_DBNAME']; // dynamic assignment

            //$this->connection = new \PDO($this->MYSQL_HOST, $this->MYSQL_USER, $this->MYSQL_PASS);
            $this->connection = new \PDO('mysql:host='.$this->MYSQL_HOST,$this->MYSQL_USER,$this->MYSQL_PASS);
            $this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            echo 'Connection failed: ' . $e->getMessage();
        }
        // TODO Check connection
    }

    public function get_connection(): \PDO
    {
        if (empty($this->connection)) {
            $this->create_connection();
        }
        return $this->connection;
    }

    public function prepare(string $query): \PDOStatement
    {

        return $this->get_connection()->prepare($query);

        //return $this->get_connection()->prepare($query);
    }

    function __destruct()
    {
        try {
            if (!empty($this->connection)) $this->connection->close();
        } catch (\Throwable $err) {
        }
    }
}


