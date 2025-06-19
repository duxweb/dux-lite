<?php

namespace Core\Translation;

use Noodlehaus\Exception\ParseException;
use Symfony\Component\Translation\Exception\InvalidResourceException;
use Symfony\Component\Translation\Loader\FileLoader;

class TomlFileLoader extends FileLoader
{

    protected function loadResource(string $resource): array
    {

        try {
            $messages = \Devium\Toml\Toml::decode(file_get_contents($resource), asArray: true);
        } catch (ParseException $e) {
            throw new InvalidResourceException(sprintf('The file "%s" does not contain valid TOML: ', $resource) . $e->getMessage(), 0, $e);
        }

        if (!\is_array($messages)) {
            throw new InvalidResourceException(sprintf('Unable to load file "%s".', $resource));
        }

        return $messages ?: [];
    }
}
