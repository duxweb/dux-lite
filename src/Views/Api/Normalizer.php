<?php

declare(strict_types=1);

namespace Core\Views\Api;

use ArrayObject;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ResponseFactory;

class Normalizer
{
    private ResponseFactory $responseFactory;

    public function __construct(?ResponseFactory $responseFactory = null)
    {
        $this->responseFactory = $responseFactory ?? new ResponseFactory();
    }

    /**
     * 标准化 fetch() 的返回结构为 [data, meta]。
     */
    public function normalizeFetchResult(mixed $res): iterable
    {
        if (!$res instanceof ResponseInterface) {
            $payload = is_string($res) ? $res : json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $resp = $this->responseFactory->createResponse(200)->withHeader('Content-Type', 'application/json');
            $resp->getBody()->write((string) ($payload ?? ''));
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
        return ['data' => $data, 'meta' => null];
    }

    /**
     * 直调调用的返回兼容格式。
     */
    public function normalizeDirectResult(mixed $res): iterable
    {
        return is_array($res) ? $res : ['data' => $res, 'meta' => null];
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
     */
    public function toArrayObject(mixed $val): mixed
    {
        if ($val === null) {
            return null;
        }
        if (is_array($val)) {
            return new ArrayObject($val, ArrayObject::ARRAY_AS_PROPS);
        }
        if (is_object($val)) {
            return new ArrayObject((array) $val, ArrayObject::ARRAY_AS_PROPS);
        }
        return $val;
    }

    /**
     * 为 foreach 提供安全的可迭代对象。
     */
    public function iterableOf(mixed $data): iterable
    {
        return is_iterable($data) ? $data : [];
    }
}
