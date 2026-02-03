<?php

declare(strict_types=1);

namespace Core\Views\Api;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\UriFactory;

class Invoker
{
    private ServerRequestFactory $requestFactory;
    private UriFactory $uriFactory;
    private ResponseFactory $responseFactory;

    public function __construct(
        ?ServerRequestFactory $requestFactory = null,
        ?UriFactory $uriFactory = null,
        ?ResponseFactory $responseFactory = null
    ) {
        $this->requestFactory = $requestFactory ?? new ServerRequestFactory();
        $this->uriFactory = $uriFactory ?? new UriFactory();
        $this->responseFactory = $responseFactory ?? new ResponseFactory();
    }

    /**
     * 调用 "Class::method"，传入查询参数与可选路径参数。
     */
    public function invoke(string $class, string $method, array $query = [], array $pathArgs = []): mixed
    {
        if (!class_exists($class)) {
            throw new \RuntimeException('Class not found: ' . $class);
        }
        $obj = new $class();
        if (!method_exists($obj, $method)) {
            throw new \RuntimeException('Method not found: ' . $class . '::' . $method);
        }
        $ref = new \ReflectionMethod($obj, $method);
        $args = $this->buildInvokeArgs($ref, $query, $pathArgs);
        if ($ref->isStatic()) {
            return $ref->invokeArgs(null, $args);
        }
        return $ref->invokeArgs($obj, $args);
    }

    /**
     * 便捷直调 "Class::method"。
     */
    public function invokeDirect(string $class, string $method, array $query = []): mixed
    {
        if (!class_exists($class)) {
            throw new \RuntimeException('Class not found: ' . $class);
        }
        $obj = new $class();
        if (!method_exists($obj, $method)) {
            throw new \RuntimeException('Method not found: ' . $class . '::' . $method);
        }
        return $obj->{$method}($query);
    }

    /**
     * 匹配常见控制器方法签名并构造调用参数。
     *
     * @return array<int,mixed>
     */
    private function buildInvokeArgs(\ReflectionMethod $ref, array $query, array $routeArgs): array
    {
        $params = $ref->getParameters();
        $argc = count($params);
        if ($argc === 0) {
            return [];
        }
        $req = $this->makeRequest($query);
        $res = $this->makeResponse();

        if ($argc >= 3) {
            return [$req, $res, $routeArgs];
        }
        if ($argc === 2) {
            $p0 = $params[0];
            $p1 = $params[1];
            $t0 = $p0->getType();
            $t1 = $p1->getType();
            $n0 = $t0 && method_exists($t0, 'getName') ? $t0->getName() : null;
            $n1 = $t1 && method_exists($t1, 'getName') ? $t1->getName() : null;
            if ($n0 && is_a($n0, ServerRequestInterface::class, true) && $n1 && is_a($n1, ResponseInterface::class, true)) {
                return [$req, $res];
            }
            if ($n0 && is_a($n0, ServerRequestInterface::class, true) && ($n1 === 'array' || $p1->getName() === 'args')) {
                return [$req, $routeArgs];
            }
            if ($n0 && is_a($n0, ResponseInterface::class, true) && ($n1 === 'array' || $p1->getName() === 'args')) {
                return [$res, $routeArgs];
            }
            return [$req, $routeArgs];
        }
        $p = $params[0];
        $t = $p->getType();
        $n = $t && method_exists($t, 'getName') ? $t->getName() : null;
        if ($n && is_a($n, ServerRequestInterface::class, true)) {
            return [$req];
        }
        if ($n === 'array' || $p->getName() === 'args' || $p->getName() === 'query') {
            return [$query];
        }
        return [$query];
    }

    /**
     * 根据查询参数构造 ServerRequest，适配控制器常见签名。
     */
    private function makeRequest(array $query): ServerRequestInterface
    {
        $uri = $this->uriFactory->createUri('/');
        return $this->requestFactory
            ->createServerRequest('GET', $uri)
            ->withQueryParams($query)
            ->withHeader('Accept', 'application/json')
            ->withHeader('X-Internal', '1')
            ->withHeader('X-Dux-Internal', '1')
            ->withAttribute('internal', true);
    }

    /**
     * 构造默认 200 响应对象。
     */
    private function makeResponse(): ResponseInterface
    {
        return $this->responseFactory->createResponse(200);
    }
}
