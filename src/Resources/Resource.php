<?php

declare(strict_types=1);

namespace Core\Resources;

use Core\App;
use Core\Permission\Permission;
use Core\Route\Route;
use Core\Auth\AuthMiddleware;

class Resource
{
    private array $authMiddleware = [];
    private array $middleware = [];

    public function __construct(public string $name, public string $route) {}

    public function addMiddleware(object ...$middleware): self
    {
        $this->middleware = $middleware;
        return $this;
    }

    public function addAuthMiddleware(object ...$middleware): self
    {
        $this->authMiddleware = $middleware;
        return $this;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getAuthMiddleware(): array
    {
        return $this->authMiddleware;
    }

    public function getAllMiddleware(): array
    {
        return array_filter([...$this->middleware, ...$this->authMiddleware]);
    }

    public function run(): void
    {
        $middle = [
            new AuthMiddleware($this->name),
            ...$this->getAllMiddleware()
        ];

        App::route()->set($this->name, new Route($this->route, $this->name, ...$middle));
        App::permission()->set($this->name, new Permission());
    }
}
