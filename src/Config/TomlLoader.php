<?php
declare(strict_types=1);

namespace Core\Config;

use Exception;
use Noodlehaus\Exception\ParseException;
use Noodlehaus\Parser\ParserInterface;

class TomlLoader implements ParserInterface
{
    protected bool $enableParsing = true;

    public function __construct(bool $enableParsing = true)
    {
        $this->enableParsing = $enableParsing;
    }

    public function parseFile($filename)
    {
        try {
            $data = \Devium\Toml\Toml::decode(file_get_contents($filename), asArray: true);
        } catch (Exception $exception) {
            throw new ParseException(
                [
                    'message'   => 'Error parsing TOML file',
                    'exception' => $exception,
                ]
            );
        }
        return $this->enableParsing ? $this->processPlaceholders($data) : $data;
    }

    public function parseString($config)
    {
        try {
            $data = \Devium\Toml\Toml::decode($config, asArray: true);
        } catch (Exception $exception) {
            throw new ParseException(
                [
                    'message'   => 'Error parsing TOML string',
                    'exception' => $exception,
                ]
            );
        }

        return $this->enableParsing ? $this->processPlaceholders($data) : $data;
    }

    /**
     * 设置函数解析开关
     */
    public function setEnableParsing(bool $enable): void
    {
        $this->enableParsing = $enable;
    }

    /**
     * 处理占位符
     */
    protected function processPlaceholders(array $data): array
    {
        return $this->recursiveProcess($data);
    }

    /**
     * 递归处理数组中的占位符
     */
    protected function recursiveProcess($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->recursiveProcess($item);
            }
            return $value;
        }

        if (is_string($value)) {
            return $this->replacePlaceholders($value);
        }

        return $value;
    }

    /**
     * 替换字符串中的占位符
     */
    protected function replacePlaceholders(string $value): string
    {
        return preg_replace_callback('/%(\w+)\(([^)]*)\)%/', function ($matches) {
            $functionName = $matches[1];
            $params = $matches[2];
            
            $paramArray = [];
            if (!empty($params)) {
                $paramArray = array_map('trim', explode(',', $params));
            }

            if ($functionName === 'env') {
                return $_ENV[$paramArray[0]] ?? getenv($paramArray[0]) ?: '';
            }

            if (function_exists($functionName)) {
                try {
                    $result = call_user_func_array($functionName, $paramArray);
                    return (string)$result;
                } catch (Exception) {
                    return '';
                }
            }

            return '';
        }, $value);
    }

    public static function getSupportedExtensions()
    {
        return ['toml'];
    }
}
