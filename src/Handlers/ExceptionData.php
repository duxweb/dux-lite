<?php
declare(strict_types=1);

namespace Core\Handlers;

use Throwable;

/**
 * ExceptionBusiness
 */
class ExceptionData  extends Exception {
    public array $data;
}