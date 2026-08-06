<?php

namespace Tests\Fakes;

use App\Contracts\RouterOsSessionFactoryInterface;
use App\Contracts\RouterOsSessionInterface;
use App\Models\Router;

class FakeRouterOsSessionFactory implements RouterOsSessionFactoryInterface
{
    public ?\Throwable $connectException = null;

    public function __construct(public FakeRouterOsSession $session) {}

    public function connect(Router $router): RouterOsSessionInterface
    {
        if ($this->connectException) {
            throw $this->connectException;
        }

        $this->session->closed = false;

        return $this->session;
    }
}
