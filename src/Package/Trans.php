<?php

namespace Dux\Package;

use Dux\App;

class Trans
{
    /**
     * 内容匹配
     * @param string $str
     * @return string
     */
    public static function contentMatch(string $str): string
    {
        //占位符匹配 处理%符号翻译后出现空格问题
        return preg_replace_callback('/%(\w+)%/', function ($matches) {
            return '{' . $matches[1] . '}';
        }, $str);
    }

    /**
     * 内容恢复
     * @param string $str
     * @return string
     */
    public static function contentRecovery(string $str): string
    {
        //占位符匹配
        return preg_replace_callback('/\{(\w+)\}/', function ($matches) {
            return '%' . $matches[1] . '%';
        }, $str);
    }

    public static function main(string $token, string $lang, array $data, string $content, callable $callback): void
    {
        $result = [];
        self::extractLeafNodes($data, $result);
        usort($result, function ($a, $b) {
            return strlen($b) - strlen($a);
        });
        $resultStr = implode("\n", array_values($result));

        //内容匹配方法
        $contentMatch = App::config('trans')->get('contentMatch', self::class . '::contentMatch');
        //内容恢复方法
        $contentRecovery = App::config('trans')->get('contentRecovery', self::class . '::contentRecovery');

        //占位符匹配 处理%符号翻译后出现空格问题
        $outputStr = $contentMatch($resultStr);

        $data = Package::request('post', '/v/services/trans', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => $token
            ],
            'json' => [
                'content' => $outputStr,
                'lang' => $lang,
                'langMaps' => App::config('trans')->get('langMaps')
            ],
            'timeout' => App::config('trans')->get('timeout', 60),
        ]);
        //内容替换
        $outContent = $contentMatch($content);
        foreach ($data as $key => $vo) {
            $tmp = $outContent;
            foreach ($vo as $item) {
                $tmp = str_replace($item['src'], $item['dst'], $tmp);
            }
            //占位符匹配 恢复
            $tmp = $contentRecovery($tmp);
            $file = $callback($key);
            file_put_contents($file, $tmp);
        }

    }

    private static function extractLeafNodes($array, &$result = []): void
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                self::extractLeafNodes($value, $result);
            } else {
                $result[] = $value;
            }
        }
    }

}