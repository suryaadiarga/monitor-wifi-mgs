<?php

namespace App\Contracts;

interface AcsAdapterInterface
{
    /** @return list<array<string, mixed>> */
    public function devices(array $filters = []): array;

    /** @return array<string, mixed> */
    public function device(string $id): array;
}
