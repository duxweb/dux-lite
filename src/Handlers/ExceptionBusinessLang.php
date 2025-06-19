<?php

declare(strict_types=1);

namespace Core\Handlers;

/**
 * ExceptionBusiness
 */
class ExceptionBusinessLang  extends Exception
{
    protected $code = 500;

    public function __construct(string $value, ...$params)
    {
        $this->message = __($value, ...$params);
    }
}
