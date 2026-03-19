<?php
declare(strict_types=1);

namespace Core\App;

use Nette\Utils\Finder;

class Attribute {

    static function normalizeParams(\ReflectionAttribute $attribute): array {
        $params = $attribute->getArguments();
        $class = $attribute->getName();
        if (!class_exists($class)) {
            return $params;
        }

        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if (!$constructor) {
            return $params;
        }

        $data = [];
        foreach ($constructor->getParameters() as $index => $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $params)) {
                $data[$name] = $params[$name];
                continue;
            }
            if (array_key_exists($index, $params)) {
                $data[$name] = $params[$index];
                continue;
            }
            if ($parameter->isDefaultValueAvailable()) {
                $data[$name] = $parameter->getDefaultValue();
            }
        }

        foreach ($params as $key => $value) {
            if (is_string($key) && !array_key_exists($key, $data)) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

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
                if (
                    $dirName === 'Test'
                    || str_starts_with($dirName, 'Test\\')
                    || str_contains($dirName, '\\Test\\')
                    || str_ends_with($dirName, '\\Test')
                ) {
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
                        'params' => self::normalizeParams($attribute)
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
                            'params' => self::normalizeParams($attribute)
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
