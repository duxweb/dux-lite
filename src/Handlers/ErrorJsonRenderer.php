<?php
declare(strict_types=1);

namespace Core\Handlers;

use Core\App;
use Slim\Error\Renderers\JsonErrorRenderer;
use Throwable;

class ErrorJsonRenderer extends JsonErrorRenderer
{
    use ErrorRendererTrait;

    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        $code = $exception->getCode() ?: 500;
        $error = ['code' => $code, 'message' => $this->getErrorTitle($exception), 'data' => []];
        return (string)json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}