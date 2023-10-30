<?php

namespace Dux\Package;

class Trans
{
    public static function main(string $username, string $password, string $lang, $name, array $data, string $content, callable $callback): void
    {
        $result = [];
        self::extractLeafNodes($data, $result);
        $resultStr = implode("\n", $result);
        $result = [];

        $data = Package::request('post', '/v/services/trans', [
            'headers' => [
                'Accept' => 'application/json'
            ],
            'auth' => [$username, $password],
            'json' => [
                'content' => $resultStr,
                'lang' => $lang,
            ]
        ]);

        foreach ($data as $key => $vo) {
            $tmp = $content;
            foreach ($vo as $item) {
                $tmp = str_replace($item['src'], $item['dst'], $tmp);
            }
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