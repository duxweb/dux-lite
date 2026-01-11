<?php

declare(strict_types=1);

namespace Core\Scheduler;

use Symfony\Contracts\EventDispatcher\Event;

class SchedulerGenEvent extends Event
{
    private array $data = [];
    private array $fallbackData = [];

    public function __construct(array $fallbackData = [])
    {
        $this->fallbackData = $fallbackData;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getFallbackData(): array
    {
        return $this->fallbackData;
    }
}
