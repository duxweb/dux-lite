<?php

declare(strict_types=1);

use Core\Views\AtFileLoader;
use Core\Views\CustomTagEngine;

it('resolves include paths via AtFileLoader', function () {
    $root = sys_get_temp_dir() . '/dux-lite-test-' . uniqid();
    $tplDir = $root . '/templates';
    $partialsDir = $tplDir . '/partials';
    $pagesDir = $tplDir . '/pages';
    @mkdir($partialsDir, 0777, true);
    @mkdir($pagesDir, 0777, true);

    $partial = $partialsDir . '/card.tpl';
    file_put_contents($partial, '<div>card</div>');
    $ref = $pagesDir . '/index.tpl';
    file_put_contents($ref, '<include src="partials/card.tpl" />');

    $loader = new AtFileLoader($tplDir);

    $resolved = $loader->getReferredName('partials/card.tpl', $ref);
    expect($loader->getContent($resolved))->toBe('<div>card</div>');

    $resolved = $loader->getReferredName($partial, $ref);
    expect($resolved)->toBe($partial);

    $resolved = $loader->getReferredName('@partials/card.tpl', $ref);
    expect($loader->getContent($resolved))->toBe('<div>card</div>');
});

it('resolves include path via sidecar when referring file is cached', function () {
    $root = sys_get_temp_dir() . '/dux-lite-test-' . uniqid();
    $tplDir = $root . '/templates';
    $partialsDir = $tplDir . '/partials';
    @mkdir($partialsDir, 0777, true);

    $partial = $partialsDir . '/card.tpl';
    file_put_contents($partial, '<div>card</div>');

    $cacheDir = $root . '/cache';
    @mkdir($cacheDir, 0777, true);
    $ref = $cacheDir . '/custom_x.latte';
    file_put_contents($ref, '{include "partials/card.tpl"}');
    file_put_contents($ref . '.srcdir', $tplDir);

    $loader = new AtFileLoader(null);
    $resolved = $loader->getReferredName('partials/card.tpl', $ref);
    expect($resolved)->toBe($partial);
});

it('keeps windows absolute cache path as unique id', function () {
    $loader = new AtFileLoader('E:/Test/dux-php-admin/theme/blog');
    $path = 'E:/Test/dux-php-admin/data/tpl/web/custom_x.latte';

    expect($loader->getUniqueId($path))->toBe($path);
});

it('emits preprocess trace callback payload', function () {
    $root = sys_get_temp_dir() . '/dux-lite-test-' . uniqid();
    $tplDir = $root . '/templates';
    $tmpDir = $root . '/cache';
    @mkdir($tplDir, 0777, true);
    @mkdir($tmpDir, 0777, true);

    $file = $tplDir . '/index.tpl';
    file_put_contents($file, '<include src="partials/card.tpl" />');

    $engine = new CustomTagEngine();
    $engine->setTempDirectory($tmpDir);

    $payload = null;
    $engine->setPreprocessTraceCallback(function (array $data) use (&$payload) {
        $payload = $data;
    });
    $engine->enablePreprocessTrace(true, $tmpDir);

    $engine->preprocessToCache($file, true);

    expect($payload)->not->toBeNull();
    expect($payload)->toHaveKey('processed');
    expect($payload['processed'])->toContain('{include');
    expect($payload)->toHaveKey('source');
});
