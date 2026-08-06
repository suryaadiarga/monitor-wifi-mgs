<?php

namespace App\Contracts;

interface VpnAdapterInterface
{
    /** @return array<string, mixed> */
    public function previewClient(array $attributes): array;

    /** @return array<string, mixed> */
    public function provisionClient(array $attributes): array;
}
