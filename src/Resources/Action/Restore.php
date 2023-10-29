<?php

namespace Dux\Resources\Action;

use Dux\App;
use Dux\Handlers\ExceptionBusiness;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

trait Restore
{
    public function restore(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->init($request, $response, $args);
        $id = $args["id"];

        App::db()->getConnection()->beginTransaction();

        $query = $this->model::query()->where($this->key, $id);
        $this->queryOne($query, $request, $args);
        $this->query($query);

        $model = $query->withTrashed()->first();
        if (!$model) {
            throw new ExceptionBusiness(__("message.emptyData", "common"));
        }

        $this->restoreBefore($model);

        $model->restore();

        $this->restoreAfter($model);

        App::db()->getConnection()->commit();

        return send($response, $this->translation($request, 'restore'));
    }

    public function restoreBefore(mixed $info): void {
    }
    public function restoreAfter(mixed $info): void {
    }

}