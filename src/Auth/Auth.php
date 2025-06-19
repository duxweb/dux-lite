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

    public static function decode(Request $request, string $app): ?array
    {
        $jwtStr = str_replace('Bearer ', '', $request->getHeaderLine('Authorization'));
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
}
