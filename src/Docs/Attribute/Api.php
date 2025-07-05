<?php
declare(strict_types=1);

namespace Core\Docs\Attribute;

use Attribute;
use Core\Docs\Enum\PayloadTypeEnum;
use Core\Docs\Enum\ResultMimeEnum;
use Core\Docs\Enum\ResultTypeEnum;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Api {

    /**
     * @param string $name 接口名称或分组名称
     * @param bool $auth 是否需要登录
     * @param PayloadTypeEnum $payloadType body 请求类型，接口请求类型
     * @param ResultMimeEnum|string $resultMime 返回类型，接口返回类型
     * @param ResultTypeEnum $resultType 返回类型，接口返回类型
     */
    public function __construct(
        public string $name,
        public bool $auth = true,
        public PayloadTypeEnum $payloadType = PayloadTypeEnum::JSON,
        public ResultMimeEnum|string $resultMime = ResultMimeEnum::JSON,
        public ResultTypeEnum $resultType = ResultTypeEnum::MESSAGE,
    ) {}


}