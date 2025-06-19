<?php

declare(strict_types=1);

namespace Core\Auth;

use Core\Handlers\ExceptionBusiness;
use Core\Utils\Attribute;
use Firebase\JWT\JWT;
use JimTools\JwtAuth\Decoder\FirebaseDecoder;
use JimTools\JwtAuth\Handlers\AfterHandlerInterface;
use JimTools\JwtAuth\Handlers\BeforeHandlerInterface;
use JimTools\JwtAuth\Options;
use JimTools\JwtAuth\Secret;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class AuthMiddleware
{
    public function __construct(
        public string $app,
        public \Closure|null $callback = null
    ) {}

    public function __invoke(Request $request, RequestHandler $handler): Response
    {

        $auth = Attribute::getRequestParams($request, "auth");

        if ($auth !== null) {
            if (!$auth) {
                return $handler->handle($request);
            }
        }

        $secret = \Core\App::config("use")->get("app.secret");
        $app = $this->app;
        $callback = $this->callback;
        $jwt = new \JimTools\JwtAuth\Middleware\JwtAuthentication(
            new Options(
                isSecure: false,
                before: new class($app) implements BeforeHandlerInterface {
                    public function __construct(public string $app) {}
                    public function __invoke(Request $request, array $arguments): Request
                    {
                        $token = $arguments["decoded"];
                        return $request->withAttribute('auth', $token)->withAttribute('app', $this->app);
                    }
                },
                after: new class($secret, $app, $callback) implements AfterHandlerInterface {
                    public function __construct(public string $secret, public string $app, public \Closure|null $callback = null) {}
                    public function __invoke(Response $response, array $arguments): Response
                    {
                        $tokenStr = $arguments["token"];
                        $token = $arguments["decoded"];
                        if ($this->app != $token["sub"]) {
                            throw new \Core\Handlers\ExceptionBusiness("Authorization app error", 401);
                        }

                        $expire =  $token["exp"] - $token["iat"];
                        $renewalTime = $token["iat"] + round($expire / 3);
                        $time = time();
                        $auth = null;
                        if ($renewalTime <= $time) {
                            $token["exp"] = $time + $expire;
                            $auth = JWT::encode($token, $this->secret, 'HS256');
                            return $response->withHeader("Authorization", "Bearer $auth");
                        }

                        if ($this->callback !== null) {
                            ($this->callback)($tokenStr, $auth);
                        }
                        return $response;
                    }
                },
            ),
            new FirebaseDecoder(new Secret($secret, 'HS256')),
        );
        try {
            return $jwt->process($request, $handler);
        } catch (\JimTools\JwtAuth\Exceptions\AuthorizationException $e) {
            throw new ExceptionBusiness('Authorization error', 401, $e);
        }
    }
}
