<?php

namespace Dux\Resources\Action;

use Closure;
use Dux\App;
use Dux\Handlers\ExceptionBusiness;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

trait DeleteMany
{
    public function deleteMany(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $params =  $request->getQueryParams();
        $this->init($request, $response, $args);
        $ids = explode(',', $params['ids']);
        if (!$ids) {
            throw new ExceptionBusiness(__("message.emptyData", "common"));
        }

        App::db()->getConnection()->beginTransaction();

        foreach ($ids as $id) {
            $query = $this->model::query()->where($this->key, $id);
            $this->queryOne($query, $request, $args);
            $this->query($query);
            $model = $query->first();
            if (!$model) {
                throw new ExceptionBusiness(__("message.emptyData", "common"));
            }

            if (isset($this->delHook[0]) && $this->delHook[0] instanceof Closure) {
                $this->delHook[0]($model);
            }

            $this->delBefore($model);

            $model->delete();

            $this->delAfter($model);
        }

        App::db()->getConnection()->commit();

        return send($response, $this->translation($request, 'delete'));
    }


}