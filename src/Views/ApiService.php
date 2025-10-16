<?php

declare(strict_types=1);

namespace Core\Views;
use ArrayObject;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\UriFactory;
use Slim\Psr7\Factory\ResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * 模板数据调用器：执行 Class::method 并标准化返回结构。
 */
class ApiService
{
    /** @var null|callable(string, array): iterable */
    private $fetcher = null;
    private ?array $lastError = null;

    /**
     * 注入自定义获取器：fn(string $route, array $query): iterable
     */
    public function setFetcher(callable $fetcher): void
    {
        $this->fetcher = $fetcher;
    }

    /**
     * @param string $route 传入 data 指定的“Class::method”（例如 "\\App\\Content\\Api\\Article::list"）
     * @param array $query  查询参数
     * @param array $pathArgs 路径参数（方法第三参 $args）
     */
    /**
     * 调用 "Class::method"，传入查询参数与可选路径参数。
     *
     * @return iterable
     */
    public function fetch(string $route, array $query = [], array $pathArgs = []): iterable
    {
        $this->lastError = null;
        if ($this->fetcher) {
            return ($this->fetcher)($route, $query);
        }
        $cm = $this->parseClassMethodRoute($route);
        if ($cm !== null) {
            [$cls, $method] = $cm;
            try {
                if (!class_exists($cls)) {
                    throw new \RuntimeException("Class not found: " . $cls);
                }
                $obj = new $cls();
                if (!method_exists($obj, $method)) {
                    throw new \RuntimeException("Method not found: " . $cls . '::' . $method);
                }
                $ref = new \ReflectionMethod($obj, $method);
                $args = $this->buildInvokeArgs($ref, $query, $pathArgs);
                $res = $ref->isStatic()
                    ? $ref->invokeArgs(null, $args)
                    : $ref->invokeArgs($obj, $args);
                if (!$res instanceof ResponseInterface) {
                    $payload = is_string($res) ? $res : json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $resp = $this->makeResponse()->withHeader('Content-Type', 'application/json');
                    $resp->getBody()->write((string)($payload ?? ''));
                    $res = $resp;
                }
                $body = (string) $res->getBody();
                $data = json_decode($body);
                if (is_object($data)) {
                    return [
                        'data' => $data?->data ?? null,
                        'meta' => $data?->meta ?? null,
                    ];
                }
                return [ 'data' => $data, 'meta' => null ];
            } catch (\Throwable $e) {
                $this->lastError = [
                    'exception' => get_debug_type($e),
                    'message' => $e->getMessage(),
                    'class' => $cls,
                    'method' => $method,
                    'query' => $query,
                ];
                return [];
            }
        }

        $this->lastError = [
            'exception' => 'InvalidRoute',
            'message' => 'data must be "Class::method" (e.g. \\App\\Content\\Api\\Article::list)',
            'route' => $route,
            'query' => $query,
        ];
        return [];
    }

    /** 仅按 data 传入的“Class::method”直接调用。 */
    /**
     * 便捷直调 "Class::method"。
     *
     * @return iterable
     */
    public function call(string $methodSpec, array $query = []): iterable
    {
        $this->lastError = null;
        $cm = $this->parseClassMethodRoute($methodSpec);
        if ($cm === null) {
            $this->lastError = [
                'exception' => 'InvalidTarget',
                'message' => 'data must be "Class::method" (e.g. \\App\\Content\\Api\\Article::list)',
                'data' => $methodSpec,
                'query' => $query,
            ];
            return [];
        }
        [$cls, $method] = $cm;
        try {
            if (!class_exists($cls)) {
                throw new \RuntimeException('Class not found: ' . $cls);
            }
            $obj = new $cls();
            if (!method_exists($obj, $method)) {
                throw new \RuntimeException('Method not found: ' . $cls . '::' . $method);
            }
            $res = $obj->{$method}($query);
            return is_array($res) ? $res : ['data' => $res, 'meta' => null];
        } catch (\Throwable $e) {
            $this->lastError = [
                'exception' => get_debug_type($e),
                'message' => $e->getMessage(),
                'class' => $cls,
                'method' => $method,
                'query' => $query,
            ];
            
            return [];
        }
    }

    /**
     * 解析 "Class::method" 为 [class, method]。
     * 仅支持命名空间类名 + 双冒号方法，如 "\\App\\Content\\Api\\Article::list"。
     *
     * @return array{0:string,1:string}|null
     */
    private function parseClassMethodRoute(string $route): ?array
    {
        // 规范化：去除包裹引号、替换全角冒号
        $route = trim($route);
        $route = trim($route, "\"' ");
        $route = str_replace('：', ':', $route);
        
        if (strpos($route, '::') === false) {
            return null;
        }
        [$classRaw, $method] = explode('::', $route, 2);
        $classRaw = trim($classRaw);
        $method = trim($method ?: 'list');

        // 仅接受命名空间类，不支持文件路径
        if ($classRaw === '' || str_ends_with($classRaw, '.php')) {
            return null;
        }
        // 允许前导反斜杠，统一为命名空间分隔符
        $cls = ltrim($classRaw, '\\');
        // 若意外含有正斜杠，转换为命名空间分隔符（宽松处理）
        $cls = str_replace('/', '\\', $cls);
        if ($cls === '') {
            return null;
        }
        return [$cls, $method];
    }

    /**
     * 根据查询参数构造 ServerRequest，适配控制器常见签名。
     */
    private function makeRequest(array $query): ServerRequestInterface
    {
        $uri = (new UriFactory())->createUri('/');
        $req = (new ServerRequestFactory())
            ->createServerRequest('GET', $uri)
            ->withQueryParams($query)
            ->withHeader('Accept', 'application/json')
            ->withHeader('X-Internal', '1')
            ->withHeader('X-Dux-Internal', '1')
            ->withAttribute('internal', true);
        return $req;
    }

    /**
     * 构造默认 200 响应对象。
     */
    private function makeResponse(): ResponseInterface
    {
        return (new ResponseFactory())->createResponse(200);
    }

    /**
     * 构造调用参数，适配常见 Slim 控制器签名：
     * - (ServerRequestInterface $request)
     * - (array $query)
     * - (ServerRequestInterface $request, ResponseInterface $response)
     * - ($request, $response, array $args)
     */
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
     * 获取最近一次错误信息。
     *
     * @return array<string,mixed>|null
     */
    public function getLastError(): ?array
    {
        return $this->lastError;
    }

    /**
     * 标准化返回结构为 [data, meta]。
     *
     * @return array{0:mixed,1:mixed}
     */
    public function split(mixed $result): array
    {
        if (is_array($result)) {
            $data = $result['data'] ?? null;
            $meta = $result['meta'] ?? null;
            if ($data !== null || $meta !== null) {
                return [$this->toArrayObject($data), $this->toArrayObject($meta)];
            }
            return [$this->toArrayObject($result), null];
        }
        if (is_object($result)) {
            $data = $result->data ?? null;
            $meta = $result->meta ?? null;
            if ($data !== null || $meta !== null) {
                return [$this->toArrayObject($data), $this->toArrayObject($meta)];
            }
            return [$this->toArrayObject($result), null];
        }
        return [$result, null];
    }

    /**
     * 将数组或对象包裹为 ArrayObject(ARRAY_AS_PROPS)，其余原样返回。
     * 便于在模板中用对象属性或数组下标两种方式访问。
     */
    private function toArrayObject(mixed $val): mixed
    {
        if ($val === null) return null;
        if (is_array($val) || is_object($val)) {
            return new ArrayObject($val, ArrayObject::ARRAY_AS_PROPS);
        }
        return $val;
    }

    /**
     * 拉取并标准化为 [data, meta]。
     *
     * @return array{0:mixed,1:mixed}
     */
    public function fetchAndUnpack(string $route, array $params = []): array
    {
        return $this->split($this->fetch($route, $params));
    }

    /**
     * 为 foreach 提供安全的可迭代对象。
     */
    public function iterableOf(mixed $data): iterable
    {
        return is_iterable($data) ? $data : [];
    }
}
