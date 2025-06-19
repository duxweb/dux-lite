<?php
declare(strict_types=1);

namespace Core\Database\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class AutoMigrate {

    public function __construct() {
    }
}