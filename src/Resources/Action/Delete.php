<?php

namespace Dux\Resources\Action;

use Closure;
use Dux\App;
use Dux\Handlers\ExceptionBusiness;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

trait Delete
{
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->init($request, $response, $args);
        $id = $args["id"];

        App::db()->getConnection()->beginTransaction();

        $query = $this->model::query()->where($this->key, $id);
        $this->queryOne($query, $request, $args);
        $this->query($query);

        $model = $query->first();
        if (!$model) {
            throw new ExceptionBusiness(__("message.emptyData", "common"));
        }

        if (isset($this->delHook[0]) && $this->delHook[0] instanceof Closure) {
            $this->delHook[0]($model,);
        }

        $this->delBefore($model);

        $model->delete();

        $this->delAfter($model);

        App::db()->getConnection()->commit();

        return send($response, $this->translation($request, 'delete'));
    }

    public function delBefore(mixed $info): void
    {
    }

    public function delAfter(mixed $info): void
    {
    }

}