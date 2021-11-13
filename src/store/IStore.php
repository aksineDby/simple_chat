<?php

namespace MyApp\Store;

use Generator;
use MyApp\Connection;

interface IStore
{
    public function attach(Connection $item): int;

    public function getAll(): Generator;

    public function getAllArray(): array;

    public function getById(int $id): ?Connection;

    public function contains(Connection $item): bool;

    public function detach(Connection $item): void;

    public function count(): int;
}
