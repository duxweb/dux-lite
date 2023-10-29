<?php
declare(strict_types=1);

namespace Dux\Permission;

class PermissionGroup
{
    private array $data = [];

    public function __construct(public string $app, public string $name, public int $order, public string $pattern = "")
    {
    }

    public function add(string $name, bool $complete = true): self
    {
        $this->data[] = $complete ? $this->pattern . $this->name . "." . $name : $name;
        return $this;
    }

    public function get(): array
    {
        return [
            "label" => __($this->pattern . $this->name . ".name", 'manage'),
            "name" => "group:" . $this->pattern . $this->name,
            "order" => $this->order,
            "children" => array_map(function ($item) {
                $labelData = explode(".", $item);
                $label = last($labelData);

                if (in_array($label, Permission::$actions)) {
                    $label = __(  "resources.$label", "common");
                }else {
                    $label = __( $item . ".name", 'manage');
                }

                return [
                    "label" => $label,
                    "name" => $item,
                ];
            }, $this->data),
        ];
    }

    public function getData(): array
    {
        return $this->data;
    }
}