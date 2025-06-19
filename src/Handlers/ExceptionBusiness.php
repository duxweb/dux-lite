<?php
declare(strict_types=1);

namespace Core\Handlers;

/**
 * ExceptionBusiness
 */
class ExceptionBusiness  extends Exception {
    protected $code = 500;
}