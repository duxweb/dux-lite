<?php
declare(strict_types=1);

namespace Core\Config;

use Exception;
use Noodlehaus\Exception\ParseException;
use Noodlehaus\Parser\ParserInterface;

class TomlLoader implements ParserInterface
{
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
        return (array)$data;
    }

    public function parseString($config)
    {
        try {
            $data = \Devium\Toml\Toml::decode($config, asArray: true);
        } catch (Exception $exception) {
            throw new ParseException(
                [
                    'message'   => 'Error parsing YAML string',
                    'exception' => $exception,
                ]
            );
        }

        return (array)$data;
    }


    public static function getSupportedExtensions()
    {
        return ['toml'];
    }
}
