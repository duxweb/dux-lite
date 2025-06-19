<?php

declare(strict_types=1);

namespace Core\Permission;

class PermissionGroup
{
    private array $data = [];
    public static array $actions = ['list', 'show', 'create', 'edit', 'store', 'delete'];

    public function __construct(public string $app, public string $name, public string $label) {}

    public function add(string $name): void
    {
        $this->data[] = [
            'name' => $this->name . "." . $name
        ];
    }

    public function label(string $label): self
    {
        $this->label = $label;
        return $this;
    }



    public function resources(array|false $actions = [], bool $softDelete = false): self
    {
        if ($actions === false) {
            return $this;
        }

        if (!$actions) {
            $actions = self::$actions;
        }

        $actions = array_intersect(self::$actions, $actions);

        if ($softDelete) {
            $actions = [...$actions, 'trash', 'restore'];
        }

        foreach ($actions as $vo) {
            $this->add($vo);
        }

        return $this;
    }

    public function get(): array
    {
        return [
            "name" => "group:" . $this->name,
            "children" => $this->data,
        ];
    }

    public function getData(): array
    {
        return $this->data;
    }
}
