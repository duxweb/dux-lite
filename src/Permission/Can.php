<?php

namespace Core\Permission;

use Core\App;
use Core\Handlers\ExceptionBusiness;
use Psr\Http\Message\ServerRequestInterface;

class Can
{

    public static function check(ServerRequestInterface $request, string $model, string $name): void
    {
        $auth = $request->getAttribute("auth");
        $uid = $auth['id'];

        $allPermission = App::permission()->get($auth['sub'])->getData();
        if (!$allPermission || !in_array($name, $allPermission)) {
            return;
        }

        $permission = $request->getAttribute("permission");

        if (!$permission) {
            $userInfo = (new $model)->query()->find($uid);
            $request->withAttribute("permission", $userInfo->permission);
        }
        if ($permission && !in_array($name, $permission)) {
            throw new ExceptionBusiness('The user does not have permission', 403);
        }
    }
}
