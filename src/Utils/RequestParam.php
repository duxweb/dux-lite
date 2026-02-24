<?php

declare(strict_types=1);

namespace Core\Utils;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final class RequestParam
{
    /**
     * @param array<string,mixed> $defaults
     * @return array<string,mixed>
     */
    public static function query(ServerRequestInterface $request, array $defaults = []): array
    {
        $params = $request->getQueryParams();
        $params = is_array($params) ? $params : [];
        return $params + $defaults;
    }

    /**
     * @param array<string,mixed> $defaults
     * @return array<string,mixed>
     */
    public static function body(ServerRequestInterface $request, array $defaults = []): array
    {
        $params = $request->getParsedBody();
        $params = is_array($params) ? $params : [];
        return $params + $defaults;
    }

    /**
     * @param array<array-key,mixed> $defaults
     * @return array<array-key,mixed>
     */
    public static function files(ServerRequestInterface $request, array $defaults = []): array
    {
        $files = $request->getUploadedFiles();
        return $files + $defaults;
    }

    /**
     * @param string|array<int,string|int> $name
     * @return UploadedFileInterface|array<array-key,mixed>|null
     */
    public static function file(ServerRequestInterface $request, string|array $name, mixed $default = null): mixed
    {
        return data_get(self::files($request), $name, $default);
    }
}
