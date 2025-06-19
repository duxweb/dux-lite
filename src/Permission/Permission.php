<?php
declare(strict_types=1);

namespace Core\Permission;

class Permission
{

    private array $data = [];
    private string $pattern;
    private string $app = '';

    public function __construct(string $pattern = "")
    {
        $this->pattern = $pattern;
    }

    public function setApp(string $app): void
    {
        $this->app = $app;
    }

    public function group(string $name): PermissionGroup
    {
        $group = new PermissionGroup($this->app, $name, $this->pattern);
        $this->data[] = $group;
        return $group;
    }


    public function get(): array
    {
        $data = [];
        foreach ($this->data as $vo) {
            $data[] = $vo->get();
        }
        return $data;
    }

    public function getData(): array
    {
        $data = [];
        foreach ($this->data as $vo) {
            $data = [...$data, ...$vo->getData()];
        }
        return $data;
    }

}