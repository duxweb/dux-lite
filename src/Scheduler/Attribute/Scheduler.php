<?php
declare(strict_types=1);

namespace Core\Scheduler\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Scheduler {

    public function __construct(string $cron) {
    }
}