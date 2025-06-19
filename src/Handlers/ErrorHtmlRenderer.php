<?php

declare(strict_types=1);

namespace Core\Handlers;

use Core\App;
use Slim\Error\Renderers\HtmlErrorRenderer;
use Slim\Exception\HttpException;
use Throwable;



class ErrorHtmlRenderer extends HtmlErrorRenderer
{

    use ErrorRendererTrait;

    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        $tplNotFound = App::di()->has('tpl.404');
        if ($tplNotFound) {
            $tplNotFound = App::di()->get('tpl.404');
        } else {
            $tplNotFound = dirname(__DIR__) . "/Tpl/404.latte";
        }

        $tplError = App::di()->has('tpl.error');
        if ($tplError) {
            $tplError = App::di()->get('tpl.error');
        } else {
            $tplError = dirname(__DIR__) . "/Tpl/error.latte";
        }


        $code = $exception->getCode() ?: 500;
        $title = $this->getErrorTitle($exception);
        $desc = $this->getErrorDescription($exception);

        return App::view("app")->renderToString($code == 404 ? $tplNotFound : $tplError, [
            "code" => $code,
            "title" => $title,
            "message" => $desc,
        ]);
    }
}
