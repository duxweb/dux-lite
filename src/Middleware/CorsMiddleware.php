<?php

namespace Core\Middleware;

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
        $params = $request->getQueryParams();
        Paginator::currentPageResolver(static function ($pageName = 'page') use ($params) {
            $page = $params[$pageName];
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
