<?php

declare(strict_types=1);

namespace Core\Auth;

use Core\App;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ServerRequestInterface as Request;

class Auth
{

    public static function token(string $app, $params = [], int $expire = 86400): string
    {
        $time = time();
        $payload = [
            'sub' => $app,
            'iat' => $time,
            'exp' => $time + $expire,
        ];
        $payload = [...$payload, ...$params];
        return 'Bearer ' . JWT::encode($payload, App::config("use")->get("app.secret"), 'HS256');
    }

    public static function decode(Request $request, string $app, string $source = 'auto'): ?array
    {
        $jwtStr = self::extractToken($request, $source);
        if (!$jwtStr) {
            return null;
        }

        try {
            $jwt = JWT::decode($jwtStr, new Key(App::config("use")->get("app.secret"), 'HS256'));
        } catch (\Exception $e) {
            return null;
        }
        if (!$jwt->sub || !$jwt->id) {
            return null;
        }
        if ($jwt->sub !== $app) {
            return null;
        }
        return (array) $jwt;
    }

    /**
     * 从请求中提取 token
     *
     * @param Request $request HTTP 请求对象
     * @param string $source 来源：'auto'(自动检测), 'header'(仅从header), 'cookie'(仅从cookie)
     * @return string|null 提取的 token 字符串（不含 Bearer 前缀）
     */
    private static function extractToken(Request $request, string $source): ?string
    {
        switch ($source) {
            case 'header':
                // 仅从 Authorization header 获取
                $header = $request->getHeaderLine('Authorization');
                if (!empty($header) && preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
                    return $matches[1];
                }
                return null;

            case 'cookie':
                // 仅从 cookie 获取
                $cookieParams = $request->getCookieParams();
                if (isset($cookieParams['token'])) {
                    $cookieValue = $cookieParams['token'];
                    // 如果 cookie 中包含 Bearer 前缀，去掉它
                    if (preg_match('/Bearer\s+(.*)$/i', $cookieValue, $matches)) {
                        return $matches[1];
                    }
                    return $cookieValue;
                }
                return null;

            case 'auto':
            default:
                // 自动检测：优先 header，然后 cookie（与 JwtAuth 库逻辑一致）
                $header = $request->getHeaderLine('Authorization');
                if (!empty($header) && preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
                    return $matches[1];
                }

                $cookieParams = $request->getCookieParams();
                if (isset($cookieParams['token'])) {
                    $cookieValue = $cookieParams['token'];
                    if (preg_match('/Bearer\s+(.*)$/i', $cookieValue, $matches)) {
                        return $matches[1];
                    }
                    return $cookieValue;
                }
                return null;
        }
    }
}
