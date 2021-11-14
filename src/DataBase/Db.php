<?php
namespace MyApp;
use PDO;
use PDOException;

class Db
{
    private static $instance;

    private $dbh;

    public static function getInstance(): Db
    {
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->dbh = new PDO(
            'mysql:host='.DB_HOST.';dbname='.DB_NAME,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_PERSISTENT => true]
        );
    }

    private function __clone()
    { }

    public function query(string $query)
    {
        try {
            return $this->dbh->query($query);
        } catch (PDOException $e) {
            echo __METHOD__ . " error {$e->getMessage()}\nquery:$query" . PHP_EOL;
            return null;
        }
    }


}
