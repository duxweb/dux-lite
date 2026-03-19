<?php

declare(strict_types=1);

use Core\Views\Render;

it('renders query pagination without duplicating page param', function () {
    $html = Render::pagination([
        'base' => 'market.html?page=1',
        'page' => 2,
        'total' => 63,
        'pageSize' => 9,
        'style' => 'query',
        'query' => [
            'keyword' => 'dux',
            'system' => 'duxapp',
        ],
        'pageParam' => 'page',
        'window' => 2,
    ]);

    expect($html)->toContain('market.html?keyword=dux&amp;system=duxapp');
    expect($html)->toContain('market.html?keyword=dux&amp;system=duxapp&amp;page=3');
    expect($html)->not->toContain('page=1&amp;page=');
});

it('renders path pagination with dash style', function () {
    $html = Render::pagination([
        'base' => 'market',
        'page' => 2,
        'total' => 63,
        'pageSize' => 9,
        'style' => 'path',
        'window' => 2,
    ]);

    expect($html)->toContain('href="market"');
    expect($html)->toContain('href="market-3"');
    expect($html)->toContain('href="market-7"');
});

it('renders segment pagination style', function () {
    $html = Render::pagination([
        'base' => 'market',
        'page' => 2,
        'total' => 63,
        'pageSize' => 9,
        'style' => 'segment',
        'separator' => '/page/',
        'window' => 2,
    ]);

    expect($html)->toContain('href="market/page/3"');
    expect($html)->toContain('href="market/page/7"');
});

it('supports custom pagination classes', function () {
    $html = Render::pagination([
        'base' => 'market',
        'page' => 2,
        'total' => 63,
        'pageSize' => 9,
        'style' => 'path',
        'class' => 'pagination custom-pagination',
        'itemClass' => 'page-btn custom-btn',
        'activeClass' => 'is-active current',
        'disabledClass' => 'is-disabled no-click',
        'ellipsisClass' => 'page-ellipsis dots',
    ]);

    expect($html)->toContain('class="pagination custom-pagination"');
    expect($html)->toContain('class="page-btn custom-btn is-active current"');
    expect($html)->toContain('class="page-ellipsis dots"');
});
