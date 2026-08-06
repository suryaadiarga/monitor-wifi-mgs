<?php

namespace App\Contracts;

use App\Models\Router;

interface RouterOsSessionFactoryInterface
{
    public function connect(Router $router): RouterOsSessionInterface;
}
