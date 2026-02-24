<?php

namespace Core\Middleware;

use Core\Utils\RequestParam;
use Illuminate\Pagination\Paginator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CorsMiddleware implements MiddlewareInterface
{
    public function __construct() {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $params = RequestParam::query($request);
        Paginator::currentPageResolver(static function ($pageName = 'page') use ($params) {
            $page = $params[$pageName] ?? null;
            if ((int)$page >= 1) {
                return $page;
            }
            return 1;
        });

        $response = $handler->handle($request);

        return $response->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', '*')
            ->withHeader('Access-Control-Allow-Headers', '*')
            ->withHeader('Access-Control-Expose-Methods', '*')
            ->withHeader('Access-Control-Expose-Headers', '*')
            ->withHeader('Access-Control-Allow-Credentials', 'true');
    }
}
