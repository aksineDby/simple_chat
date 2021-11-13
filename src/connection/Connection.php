<?php
namespace MyApp;

class Connection
{
    private $resource;
    private $id;
    private $login = '';
    private $ip = '';
    private static $allConnections = [];

    public static function new($resource): Connection
    {
        if (!isset(self::$allConnections[(int)$resource])) {
            self::$allConnections[(int)$resource] = new self($resource);
        }
        return self::$allConnections[(int)$resource];
    }

    private function __construct(&$resource)
    {
        $this->resource = &$resource;
        $this->id = (int) $resource;
    }

    private function __clone() {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function disconnect()
    {
        if (isset(self::$allConnections[$this->getId()])) {
            unset(self::$allConnections[$this->getId()]);
        }
        if (is_resource($this->getResource())) {
            fclose($this->getResource());
        }
    }

    public function setLogin(string $login): Connection
    {
        $this->login = $login;
        return $this;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    public function setIp(string $ip): Connection
    {
        $this->ip = $ip;
        return $this;
    }

    public function getIp(): string
    {
        return $this->ip;
    }
}
