<?php
declare(strict_types=1);

namespace Core\Command\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Command {

    public function __construct() {
    }
}