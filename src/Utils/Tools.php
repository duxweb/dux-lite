<?php

namespace Dux\Utils;

use Dux\App;
use Dux\Handlers\ExceptionBusiness;
use RedisException;

class Tools
{

    /**
     * @param string $lockKey
     * @param int $lockTimeout
     * @param int $maxWaitTime
     * @return void
     * @throws RedisException
     */
    public static function lock(string $lockKey, int $lockTimeout = 5, int $maxWaitTime = 5): void
    {
        // 抢占锁
        $waitTime = 0;
        $lockStatus = false;
        while ($waitTime <= $maxWaitTime) {
            $result = App::redis()->set($lockKey, '1', [
                'ex' => $lockTimeout,
                'nx'
            ]);
            if ($result === true) {
                $lockStatus = true;
                break;
            }
            usleep(500000);
            $waitTime += 0.5;
        }

        if (!$lockStatus) {
            throw new ExceptionBusiness('当前业务繁忙中，请重试');
        }
    }

    public static function unlock($lockKey): void
    {
        App::redis()->del($lockKey);
    }
}