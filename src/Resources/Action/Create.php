<?php

namespace Dux\Resources\Action;

use Dux\App;
use Dux\Validator\Data;
use Dux\Validator\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

trait Create
{

    public function create(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->init($request, $response, $args);

        $requestData = $request->getParsedBody() ?: [];
        $data = Validator::parser($requestData, $this->validator($requestData, $request, $args));
        App::db()->getConnection()->beginTransaction();

        $modelData = $this->formatData($this->format($data, $request, $args), $data);

        $model = new $this->model;
        foreach ($modelData as $key => $vo) {
            $model->$key = $vo;
        }

        $this->createBefore($data);

        $model->save();

        $this->createAfter($data, $model);

        App::db()->getConnection()->commit();

        return send($response, $this->translation($request, 'create'));
    }

    public function createBefore(Data $data): void
    {
    }

    public function createAfter(Data $data, mixed $info): void
    {
    }

}