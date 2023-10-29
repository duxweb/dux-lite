<?php

namespace Dux\Resources\Action;

use Illuminate\Database\Eloquent\Builder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

trait Many
{
    public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->init($request, $response, $args);
        $queryParams = $request->getQueryParams();

        if (!isset($queryParams["pageSize"])) {
            $this->pagination['status'] = false;
        }

        $limit = 0;
        if ($this->pagination['status']) {
            $limit = $queryParams["pageSize"] ?: $this->pagination['pageSize'];
        }

        /**
         * @var $query Builder
         */
        $query = $this->model::query();

        $key = $queryParams['id'];
        if ($key) {
            $query->where($this->key, $key);
        }

        $keys = array_filter(explode(',', $queryParams['ids']));
        if ($keys) {
            $query->whereIn($this->key, $keys);
        }

        $sorts = $this->getSorts($queryParams);
        foreach ($sorts as $key => $sort) {
            $query->orderBy($key, $sort);
        }

        $this->queryMany($query, $request, $args);
        $this->query($query);

        if ($this->pagination['status']) {
            $result = $query->paginate($limit);
        }else {
            if ($this->tree) {
                $result = $query->get()->toTree();
            }else {
                $result = $query->get();
            }
        }

        $assign = $this->transformData($result, function ($item): array {
            return $this->transform($item);
        });

        $meta = $this->metaMany($result, (array)$result['data'], $request, $args);

        $assign['meta'] = [
            ...$assign['meta'],
            ...$meta,
        ];

        return send($response, "ok", $assign['data'], $assign['meta']);
    }

}