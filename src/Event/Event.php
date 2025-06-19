<?php

declare(strict_types=1);

namespace Core\Event;

use Core\App;
use Core\Event\Attribute\Listener;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Event extends EventDispatcher
{

    public array $registers = [];

    public function addListener(string $eventName, callable|array $listener, int $priority = 0): void
    {
        $this->registers[$eventName][] = !is_array($listener) ? 'callable' : implode(':', [$listener[0]::class, $listener[1]]);
        parent::addListener($eventName, $listener, $priority);
    }

    public function registerAttribute(): void
    {
        $attributes = App::attributes();

        foreach ($attributes as $item) {
            foreach ($item["annotations"] as $annotation) {
                if ($annotation["name"] != Listener::class) {
                    continue;
                }
                $params = $annotation["params"];
                [$class, $method] = explode(':', $annotation["class"]);
                $this->addListener($params["name"], [new $class, $method], (int)$params["priority"] ?: 0);
            }
        }
    }
}
