<?php

declare(strict_types=1);

use Carbon\Carbon;
use Core\App;
use Core\Handlers\ExceptionBusiness;
use Symfony\Component\VarDumper\VarDumper;

if (!function_exists('dd')) {

    function dd(...$vars): void
    {
        foreach ($vars as $v) {
            VarDumper::dump($v);
        }
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ""): string
    {
        return sys_path(App::$basePath, $path);
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ""): string
    {
        return sys_path(App::$appPath, $path);
    }
}

if (!function_exists('data_path')) {
    function data_path(string $path = ""): string
    {
        return sys_path(App::$dataPath, $path);
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ""): string
    {
        return sys_path(App::$publicPath, $path);
    }
}

if (!function_exists('config_path')) {
    function config_path(string $path = ""): string
    {
        return sys_path(App::$configPath, $path);
    }
}

if (!function_exists('sys_path')) {
    function sys_path(string $base = "", string $path = ""): string
    {
        $base = rtrim(str_replace("\\", "/", $base), "/");
        $path = str_replace("\\", "/", $path ? "/" . $path : "");
        return $base . $path;
    }
}

if (!function_exists('now')) {
    function now(DateTimeZone|string|int|null $timezone = null): Carbon
    {
        return Carbon::now($timezone);
    }
}



if (!function_exists('get_ip')) {
    function get_ip()
    {
        if (getenv('HTTP_CLIENT_IP')) {
            $ip = getenv('HTTP_CLIENT_IP');
        }
        if (getenv('HTTP_X_REAL_IP')) {
            $ip = getenv('HTTP_X_REAL_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
            $ips = explode(',', $ip);
            $ip = $ips[0];
        } elseif (getenv('REMOTE_ADDR')) {
            $ip = getenv('REMOTE_ADDR');
        } else {
            $ip = '0.0.0.0';
        }
        return $ip;
    }
}

if (!function_exists('bc_format')) {
    function bc_format(int|float|string $value = 0, int $decimals = 2): string
    {
        return number_format((float)$value, $decimals, '.', '');
    }
}

if (!function_exists('bc_math')) {
    function bc_math(int|float|string $left = 0, string $symbol = '+', int|float|string $right = 0, int $default = 2): string
    {
        bcscale($default);
        return match ($symbol) {
            '+' => bcadd((string)$left, (string)$right),
            '-' => bcsub((string)$left, (string)$right),
            '*' => bcmul((string)$left, (string)$right),
            '/' => bcdiv((string)$left, (string)$right),
            '%' => bcmod((string)$left, (string)$right),
        };
    }
}

if (!function_exists('bc_comp')) {
    function bc_comp(int|float|string $left = 0, int|float|string $right = 0, int $scale = 2): int
    {
        return bccomp((string)$left, (string)$right, $scale);
    }
}

if (!function_exists('encryption')) {
    function encryption(string $str, string $key = '', string $iv = '', $method = 'AES-256-CBC'): string
    {
        $key = $key ?: App::config('use')->get('app.secret');
        $data = openssl_encrypt($str, $method, $key, OPENSSL_RAW_DATA, $iv);
        if (!$data) {
            throw new ExceptionBusiness(__('encryption.failure', 'common'));
        }
        return base64_encode($data);
    }
}


if (!function_exists('decryption')) {
    function decryption(string $str, string $key = '', string $iv = '', $method = 'AES-256-CBC'): string|false
    {
        $key = $key ?: App::config('use')->get('app.secret');
        return openssl_decrypt(base64_decode($str), $method, $key, OPENSSL_RAW_DATA, $iv);
    }
}

if (!function_exists('is_service')) {
    function is_service(): bool
    {
        return App::di()->has('server');
    }
}


if (!function_exists('__')) {
    function __(string $value, ...$params): string
    {
        $parameters = [];
        $domain = '';

        if (isset($params[0])) {
            if (is_array($params[0])) {
                $parameters = $params[0];
            } else {
                $domain = $params[0];
            }
        }

        if (isset($params[1])) {
            if (is_array($params[1])) {
                $parameters = $params[1];
            } else {
                $domain = $params[1];
            }
        }

        return App::trans()->trans($value, $parameters, $domain, App::di()->get('lang', App::$lang));
    }
}

if (!function_exists('human_filesize')) {
    function human_filesize($bytes, $decimals = 2): string
    {
        $size = ['B', 'kB', 'MB', 'GB', 'TB', 'PB'];
        $factor = floor((strlen((string)$bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }
}

if (!function_exists('str_hidden')) {
    function str_hidden(string $str, int $percent = 50, string $hide = '*', string $explode = ''): string
    {
        if ($explode) {
            $email = explode($explode, $str);
            $str = $email[0];
        }
        $length = mb_strlen($str, 'utf-8');
        $mid = floor($length / 2);
        $hideLength = floor($length * ($percent / 100));
        $start = (int)$mid - floor($hideLength / 2);
        $hideStr = '';
        for ($i = 0; $i < $hideLength; $i++) {
            $hideStr .= $hide;
        }
        if (!empty($email[1])) {
            $str .= '@' . $email[1];
        }
        return substr_replace($str, $hideStr, (int)$start, (int)$hideLength);
    }
}
