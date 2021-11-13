<?php

namespace MyApp\Store;

use Generator;
use MyApp\Connection;

class ConnectStore implements IStore
{
    private $connections;
    private $connectionsById = [];

    private static $instance;

    private function __construct()
    {
        $this->connections = new \SplObjectStorage;
    }

    private function __clone()
    { }

    public static function getInstance(): IStore
    {
        if (is_null(self::$instance)){
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function getAllArray(): array
    {
        return $this->connectionsById;
    }

    public function getAll(): Generator
    {
        foreach ($this->connections as $k => $u){
            yield $u;
        }
    }

    public function getById(int $id): ?Connection
    {
        return $this->connectionsById[$id] ?? null;
    }

    public function attach(Connection $item): int
    {
        $this->connections->attach($item);
        $this->connectionsById[$item->getId()] = $item;
        return $item->getId();
    }

    public function contains(Connection $item): bool
    {
        return $this->connections->contains($item);
    }

    public function detach(Connection $item): void
    {
        if ($this->contains($item)){
            $this->connections->detach($item);
        }
        $item->disconnect();
    }

    public function count(): int
    {
        return $this->connections->count();
    }
}
