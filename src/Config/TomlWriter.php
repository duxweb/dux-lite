<?php
declare(strict_types=1);

namespace Core\Config;

use Noodlehaus\Writer\AbstractWriter;

class TomlWriter extends AbstractWriter
{

    public function toString($config, $pretty = true)
    {
        return \Devium\Toml\Toml::encode($config);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSupportedExtensions()
    {
        return ['toml'];
    }
}