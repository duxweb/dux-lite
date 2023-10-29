<?php

namespace Dux\Manage;

use DI\DependencyException;
use DI\NotFoundException;
use Dux\App;
use Dux\Handlers\ExceptionBusiness;
use Dux\Validator\Data;
use Dux\Validator\Validator;
use Illuminate\Database\Eloquent\Builder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * @method global(ServerRequestInterface $request, ResponseInterface $response, array $args): void
 * @method globalWhere(Builder $query): Builder
 * @method listWhere(Builder $query, array $args, ServerRequestInterface $request): Builder
 * @method listFormat(object $item): array
 * @method listAssign($query, array $args, array $list): array
 * @method infoWhere(Builder $query, array $args, ServerRequestInterface $request): Builder
 * @method infoAssign($info, ServerRequestInterface $request): array
 * @method infoFormat($info, ServerRequestInterface $request): array
 * @method saveValidator(array $args, ServerRequestInterface $request): array
 * @method saveFormat(Data $data, int $id, ServerRequestInterface $request): array
 * @method saveBefore(Data $data, $info, int $id)
 * @method saveAfter(Data $data, $info, int $id)
 * @method saveEnd(Data $data, $info, int $id)
 * @method storeBefore(array $updateData, int $id, $data)
 * @method storeAfter($info, array $updateData, $data)
 * @method delWhere(Builder $query, array $args): Builder
 * @method delBefore($info, array $args)
 * @method delAfter($info, array $args)
 * @method trashedWhere(Builder $query, array $args): Builder
 * @method trashedBefore($info, array $args)
 * @method trashedAfter($info, array $args)
 * @method restoreWhere(Builder $query, array $args): Builder
 * @method restoreBefore($info, array $args)
 * @method restoreAfter($info, array $args)
 */
class Manage
{

    protected string $id = "id";
    protected string $name = "";
    protected string $model = "";
    protected bool $tree = false;
    protected int $listLimit = 20;
    protected array $listFields = [];
    protected bool $listPage = true;

    /**
     * 获取列表
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (method_exists($this, 'global')) {
            $this->global($request, $response, $args);
        }
        $queryParams = $request->getQueryParams();
        $limit = $queryParams["limit"] ?: $this->listLimit;
        $treeStatus = $this->tree;
        $pageStatus = $this->listPage;
        /**
         * @var $query Builder
         */
        $query = $this->model::query();

        $key = $queryParams['key'];
        if ($key) {
            $query->where('id', $key);
        }

        if ($this->listFields) {
            $query = $query->select($this->listFields);
        }

        if (method_exists($this, "listWhere")) {
            $query = $this->listWhere($query, $args, $request);
        }

        if (method_exists($this, "globalWhere")) {
            $query = $this->globalWhere($query);
        }

        if ($pageStatus && !$treeStatus) {
            $query = $query->paginate($limit);
        } else {
            $query = $query->get();
        }
        if($treeStatus){
            $query = $query->toTree();
        }

        $assign = [];
        if (method_exists($this, "listFormat")) {
            $assign = format_data($query, function ($item): array {
                return $this->listFormat($item);
            });
        } else {
            $assign = [
                "list" => $query->toArray()
            ];
        }
        if (method_exists($this, "listAssign")) {
            $assign = [...$assign, ...$this->listAssign($query, $args, (array)$assign['list'])];
        }
        return send($response, "ok", $assign);
    }

    /**
     * 获取信息
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     */
    public function info(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (method_exists($this, 'global')) {
            $this->global($request, $response, $args);
        }
        $id = $args["id"] ?: 0;
        $info = collect();
        if ($id) {
            $query = $this->model::query()->where($this->id, $id);
            if (method_exists($this, "infoWhere")) {
                $query = $this->infoWhere($query, $args, $request);
            }
            if (method_exists($this, "globalWhere")) {
                $query = $this->globalWhere($query);
            }
            $info = $query->first();
            $data = format_data($info, function ($item) use($request): array {
                return method_exists($this, "infoFormat") ? $this->infoFormat($item, $request) : $item->toArray();
            });
        } else {
            $data = [];
        }

        if (method_exists($this, "infoAssign")) {
            $data = [...$data, ...$this->infoAssign($info, $request)];
        }
        return send($response, "ok", $data);
    }

    /**
     * 保存数据
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     * @throws DependencyException
     * @throws NotFoundException
     * @throws Throwable
     */
    public function save(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (method_exists($this, 'global')) {
            $this->global($request, $response, $args);
        }
        $id = $args["id"] ?: 0;
        $name = $this->name;
        $data = Validator::parser([...$request->getParsedBody(), ...$args], method_exists($this, "saveValidator") ? $this->saveValidator($args, $request) : []);
        App::db()->getConnection()->beginTransaction();

        if (method_exists($this, "saveFormat")) {
            $modelData = $this->saveFormat($data, $id, $request);
        } else {
            $modelData = (array)$data;
        }

        if ($id) {
            $query = $this->model::query()->where($this->id, $id);
            if (method_exists($this, "saveWhere")) {
                $query = $this->infoWhere($query, $args, $request);
            }
            if (method_exists($this, "globalWhere")) {
                $query = $this->globalWhere($query);
            }
            $model = $query->first();
            if (!$model) {
                throw new ExceptionBusiness('数据不存在');
            }
        } else {
            $model = new $this->model;
        }
        foreach ($modelData as $key => $vo) {
            $model->$key = $vo;
        }

        if (method_exists($this, "saveBefore")) {
            $this->saveBefore($data, $model, $id);
        }
        $model->save();
        if (method_exists($this, "saveAfter")) {
            $this->saveAfter($data, $model, $id);
        }
        App::db()->getConnection()->commit();

        if (method_exists($this, "saveEnd")) {
            $this->saveEnd($data, $model, $id);
        }

        return send($response, ($id ? "编辑" : "添加") . "{$name}成功");
    }

    /**
     * 保存单个数据
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     * @throws DependencyException
     * @throws NotFoundException
     * @throws Throwable
     */
    public function store(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (method_exists($this, 'global')) {
            $this->global($request, $response, $args);
        }
        $id = $args["id"] ?: 0;
        $data = $request->getParsedBody();
        App::db()->getConnection()->beginTransaction();
        $updateData = [
            $data["key"] => $data["value"]
        ];
        if (method_exists($this, "storeBefore")) {
            $updateData = $this->storeBefore($updateData, $id, $data);
        }
        $model = $this->model::query()->where($this->id, $id);
        if (method_exists($this, "globalWhere")) {
            $model = $this->globalWhere($model);
        }
        $model->update($updateData);
        if (method_exists($this, "storeAfter")) {
            $this->storeAfter($model->find($id), $updateData, $data);
        }
        App::db()->getConnection()->commit();
        return send($response, "更改成功");
    }

    /**
     * 删除数据
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     * @throws DependencyException
     * @throws NotFoundException
     * @throws Throwable
     */
    public function del(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (method_exists($this, 'global')) {
            $this->global($request, $response, $args);
        }
        $id = $args["id"] ?: 0;
        $name = $this->name ?? "";
        $query = $this->model::query();
        if (method_exists($this, "delWhere")) {
            $query = $this->delWhere($query, $args);
        }
        if (method_exists($this, "globalWhere")) {
            $query = $this->globalWhere($query);
        }
        $info = $query->where($this->id, $id)->first();

        App::db()->getConnection()->beginTransaction();
        if (method_exists($this, "delBefore")) {
            $this->delBefore($info, $args);
        }
        $info->delete();
        if (method_exists($this, "delAfter")) {
            $this->delAfter($info, $args);
        }
        App::db()->getConnection()->commit();
        return send($response, "删除{$name}成功");
    }

    /**
     * 清除数据
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     * @throws DependencyException
     * @throws NotFoundException
     * @throws Throwable
     */
    public function trashed(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (method_exists($this, 'global')) {
            $this->global($request, $response, $args);
        }
        $id = $args["id"] ?: 0;
        $name = $this->name ?? "";
        $query = $this->model::query();
        if (method_exists($this, "trashedWhere")) {
            $query = $this->trashedWhere($query, $args);
        }
        if (method_exists($this, "globalWhere")) {
            $query = $this->globalWhere($query);
        }
        $info = $query->where($this->id, $id)->withTrashed()->first();

        App::db()->getConnection()->beginTransaction();
        if (method_exists($this, "trashedBefore")) {
            $this->trashedBefore($info, $args);
        }
        $info->forceDelete();
        if (method_exists($this, "trashedAfter")) {
            $this->trashedAfter($info, $args);
        }
        App::db()->getConnection()->commit();
        return send($response, "清除{$name}成功");
    }

    /**
     * 恢复数据
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $args
     * @return ResponseInterface
     * @throws DependencyException
     * @throws NotFoundException
     * @throws Throwable
     */
    public function restore(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (method_exists($this, 'global')) {
            $this->global($request, $response, $args);
        }
        $id = $args["id"] ?: 0;
        $name = $this->name ?? "";
        $query = $this->model::query();
        if (method_exists($this, "restoreWhere")) {
            $query = $this->restoreWhere($query, $args);
        }
        if (method_exists($this, "globalWhere")) {
            $query = $this->globalWhere($query);
        }
        $info = $query->where($this->id, $id)->withTrashed()->first();

        App::db()->getConnection()->beginTransaction();
        if (method_exists($this, "restoreBefore")) {
            $this->restoreBefore($info, $args);
        }
        $info->restore();
        if (method_exists($this, "restoreAfter")) {
            $this->restoreAfter($info, $args);
        }
        App::db()->getConnection()->commit();
        return send($response, "清除{$name}成功");
    }
}