<?php

namespace App\Contracts;

interface RouterOsSessionInterface
{
    /** @return list<array<string, mixed>> */
    public function query(string $command, array $properties): array;

    public function close(): void;
}
