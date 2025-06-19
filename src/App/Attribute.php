<?php
declare(strict_types=1);

namespace Core\App;

use Nette\Utils\Finder;

class Attribute {

    static function load(array $apps): array {
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
}