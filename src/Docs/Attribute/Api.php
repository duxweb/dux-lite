<?php
declare(strict_types=1);

namespace Core\Docs\Attribute;

use Attribute;
use Core\Docs\Enum\PayloadTypeEnum;
use Core\Docs\Enum\ResultMimeEnum;
use Core\Docs\Enum\ResultTypeEnum;

#[Attribute(Attribute::TARGET_CLASS)]
class Api {

    /**
     * @param string $name 接口名称
     * @param PayloadTypeEnum $payloadType body 请求类型，接口请求类型
     * @param ResultMimeEnum|string $resultMime 返回类型，接口返回类型
     * @param ResultTypeEnum $resultType 返回类型，接口返回类型
     * @param mixed $payloadExample 请求示例
     * @param mixed $resultExample 返回示例
     */
    public function __construct(
        public string $name,
        public PayloadTypeEnum $payloadType = PayloadTypeEnum::JSON,
        public ResultMimeEnum|string $resultMime = ResultMimeEnum::JSON,
        public ResultTypeEnum $resultType = ResultTypeEnum::MESSAGE,
        public mixed $payloadExample = null,
        public mixed $resultExample = null,
    ) {}


}