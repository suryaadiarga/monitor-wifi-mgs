<?php

namespace Tests\Fakes;

use App\Contracts\RouterOsSessionInterface;

class FakeRouterOsSession implements RouterOsSessionInterface
{
    public array $calls = [];

    public bool $closed = false;

    public function __construct(public array $responses = []) {}

    public function query(string $command, array $properties): array
    {
        $this->calls[] = compact('command', 'properties');
        $response = $this->responses[$command] ?? [];
        if ($response instanceof \Throwable) {
            throw $response;
        }

        return $response;
    }

    public function close(): void
    {
        $this->closed = true;
    }
}
