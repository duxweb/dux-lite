<?php
declare(strict_types=1);
namespace Dux\Handlers;

use Dux\App;
use Slim\Error\Renderers\HtmlErrorRenderer;
use Throwable;



class ErrorHtmlRenderer extends HtmlErrorRenderer
{
    use ErrorRendererTrait;

    public function __construct() {
        $this->defaultErrorTitle = App::$bootstrap->exceptionTitle;
        $this->defaultErrorDescription = App::$bootstrap->exceptionDesc;
    }

    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        if ($displayErrorDetails) {
            return parent::__invoke($exception, true);
        } else {
            return App::$bootstrap->view->renderToString(dirname(__DIR__) . "/Tpl/error.html", [
                "code" => $exception->getCode(),
                "title" => $this->getErrorTitle($exception),
                "desc" => $this->getErrorDescription($exception),
                "back" => App::$bootstrap->exceptionBack,
            ]);

        }
    }
}