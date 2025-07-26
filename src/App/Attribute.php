<?php
declare(strict_types=1);

namespace Core\App;

use Nette\Utils\Finder;

class Attribute {

    static function load(array $apps, bool $docs = false): array {
        $data = [];
        foreach ($apps as $vo) {
            $reflection = new \ReflectionClass($vo);
            $appDir = dirname($reflection->getFileName());
            $appDirLen = strlen($appDir);
            $files = Finder::findFiles("*/*.php")->from($appDir);

            $attributes = [];
            foreach ($files as $file) {
                $dirName = str_replace('/','\\',substr($file->getPath(),$appDirLen + 1));
                if (str_ends_with($dirName, 'Test')) {
                    continue;
                }
                $class = $reflection->getNamespaceName() . "\\" . $dirName . "\\" . $file->getBasename(".php");
                if (!class_exists($class)) {
                    continue;
                }

                if ($docs && str_starts_with($class, 'Core\\Docs\\Attribute\\')) {
                    continue;
                }

                $classRef = new \ReflectionClass($class);
                $attributes = $classRef->getAttributes();


                $classAttributes = [
                    'class' => $class,
                    'annotations' => []
                ];

                foreach ($attributes as $attribute) {
                    if (!isset($data[$attribute->getName()]) && !class_exists($attribute->getName())) {
                        continue;
                    }
                    $classAttributes['annotations'][] = [
                        'name' => $attribute->getName(),
                        'class' => $class,
                        'params' => $attribute->getArguments()
                    ];
                }

                $methods = $classRef->getMethods();
                foreach ($methods as $method) {

                    if ($docs && str_starts_with($method->getDeclaringClass()->getName(), 'Core\\Docs\\Attribute\\')) {
                        continue;
                    }

                    $attributes = $method->getAttributes();
                    foreach ($attributes as $attribute) {
                        if (!isset($data[$attribute->getName()]) && !class_exists($attribute->getName())) {
                            continue;
                        }
                        $classAttributes['annotations'][] = [
                            'name' => $attribute->getName(),
                            'class' => $class . ":" . $method->getName(),
                            'method' => $method->getName(),
                            'params' => $attribute->getArguments()
                        ];
                    }
                }
                $data[] = $classAttributes;
            }

        }

        return $data;
    }

    static function getCache(array $apps): array {
        $cachePath = data_path("/cache/attributes.cache");
        if (!file_exists($cachePath)) {
            $data = self::load($apps);
            self::setCache($data);
            return $data;
        }

        $cache = file_get_contents($cachePath);
        if (!$cache) {
            $data = self::load($apps);
            self::setCache($data);
            return $data;
        }

        return unserialize($cache);
    }

    static function setCache(array $data): void {
        file_put_contents(data_path("/cache/attributes.cache"), serialize($data));
    }
}