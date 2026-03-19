<?php

declare(strict_types=1);

namespace Core\Worker;

use Core\App;

class Worker
{

    /**
     * 运行 Worker
     * @param int $maxRequests 最大请求数
     * @return void
     */
    public static function run(int $maxRequests = 0) : void {

        $handler = static function (): void {
            try {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Methods: *');
                header('Access-Control-Allow-Headers: *');
                header('Access-Control-Allow-Credentials: true');

                App::$bootstrap->runWeb();

            } catch (\Throwable $e) {
                error_log("Worker request error: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());

                http_response_code(500);
                header('Content-Type: application/json');

                echo json_encode([
                    'error' => 'Internal Server Error',
                    'message' => App::$debug ? $e->getMessage() : 'Something went wrong'
                ]);
            }
        };

        for ($nbRequests = 0; !$maxRequests || $nbRequests < $maxRequests; ++$nbRequests) {
            $keepRunning = \frankenphp_handle_request($handler);

            self::reset();

            if ($nbRequests % 100 === 0) {
                gc_collect_cycles();
            }

            if (!$keepRunning) {
                break;
            }
        }

    }

    /**
     * 重置请求
     * @return void
     */
    private static function reset(): void
    {
        App::context()->clear();
    }

}
