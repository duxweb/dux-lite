<?php

declare(strict_types=1);

use Core\Views\CustomTagPreprocessor;
use Core\Views\Render;
use Core\Views\ApiService;

class DemoApiHandler
{
    public function list($request, $response, array $args): array
    {
        return [
            'data' => ['ok' => true],
            'meta' => ['page' => 1],
        ];
    }

    public function simple(array $query): array
    {
        return ['data' => $query, 'meta' => null];
    }
}

it('transforms list tag into xTagAssign and foreach', function () {
    $tpl = '<list data="App\\Demo\\Api\\Article::list" as="items"><div>{$items.title}</div></list>';
    $pp = new CustomTagPreprocessor();
    $out = $pp->preprocess($tpl);

    expect($out)->toContain('{xTagAssign');
    expect($out)->toContain('{foreach $list as $items}');
});

it('transforms info tag into xTagAssign with custom var', function () {
    $tpl = '<info data="App\\Demo\\Api\\Article::detail" as="item" />';
    $pp = new CustomTagPreprocessor();
    $out = $pp->preprocess($tpl);

    expect($out)->toContain('{xTagAssign');
    expect($out)->toContain("'var' => 'item'");
});

it('rewrites dotted placeholders into x-val macros', function () {
    $tpl = '<user.profile />';
    $pp = new CustomTagPreprocessor();
    $out = $pp->preprocess($tpl);

    expect($out)->toContain('data_get($user, \'profile\')');
});

it('supports filter chain on dotted placeholders', function () {
    $tpl = '<user.profile upper cut="10" />';
    $pp = new CustomTagPreprocessor();
    $out = $pp->preprocess($tpl);

    expect($out)->toContain('data_get($user, \'profile\')');
    expect($out)->toContain('|upper');
    expect($out)->toContain('|cut:\'10\'');
});

it('transforms pagination tag into pagination macro', function () {
    $tpl = '<pagination base="/articles" :page="$meta[\'page\']" pageParam="p" />';
    $pp = new CustomTagPreprocessor();
    $out = $pp->preprocess($tpl);

    expect($out)->toContain('{pagination');
    expect($out)->toContain("base => '/articles'");
    expect($out)->toContain('page => $meta[\'page\']');
    expect($out)->toContain("pageparam => 'p'");
});

it('renders pagination html with expected links', function () {
    $html = Render::pagination([
        'base' => '/articles',
        'page' => 2,
        'total' => 50,
        'pageSize' => 10,
        'pageParam' => 'page',
        'window' => 1,
    ]);

    expect($html)->toContain('class="pagination"');
    expect($html)->toContain('page=1');
    expect($html)->toContain('page=2');
    expect($html)->toContain('page=3');
    expect($html)->toContain('is-active');
});

it('supports x- prefix tags for builtins', function () {
    $tpl = '<x-list data="App\\\\Demo\\\\Api\\\\Article::list"><span>{$listItem.title}</span></x-list>';
    $pp = new CustomTagPreprocessor();
    $out = $pp->preprocess($tpl);

    expect($out)->toContain('{xTagAssign');
    expect($out)->toContain('{foreach $list as $listItem}');
});

it('parses expression attributes and raw attributes consistently', function () {
    $tpl = '<list data="App\\\\Demo\\\\Api\\\\Article::list" :path="[\'category\']" category="$tab" page="1"></list>';
    $pp = new CustomTagPreprocessor();
    $out = $pp->preprocess($tpl);

    expect($out)->toContain("'path' => ['category']");
    expect($out)->toContain("'params' => [");
    expect($out)->toContain('"category" => $tab');
    expect($out)->toContain('"page" => "1"');
});

it('builds include macro with src and params', function () {
    $tpl = '<include src="@inc/card.tpl" title="Hello" :count="$count" />';
    $pp = new CustomTagPreprocessor();
    $out = $pp->preprocess($tpl);

    expect($out)->toContain("{include '@inc/card.tpl'");
    expect($out)->toContain("'title' => 'Hello'");
    expect($out)->toContain("'count' => \$count");
});

it('supports include path variants', function () {
    $pp = new CustomTagPreprocessor();

    $out = $pp->preprocess('<include src="partials/card.tpl" />');
    expect($out)->toContain("{include 'partials/card.tpl'");

    $out = $pp->preprocess('<include src="/views/partials/card.tpl" />');
    expect($out)->toContain("{include '/views/partials/card.tpl'");

    $out = $pp->preprocess('<include src="@inc/card.tpl" />');
    expect($out)->toContain("{include '@inc/card.tpl'");
});

it('builds include macro with expression src', function () {
    $tpl = '<include :src="$tplPath" :data="$row" />';
    $pp = new CustomTagPreprocessor();
    $out = $pp->preprocess($tpl);

    expect($out)->toContain('{include $tplPath');
    expect($out)->toContain("'data' => \$row");
});

it('supports embed and block tags with include-like args', function () {
    $tpl = '<embed src="@inc/card.tpl" title="A"><block name="content">X</block></embed>';
    $pp = new CustomTagPreprocessor();
    $out = $pp->preprocess($tpl);

    expect($out)->toContain("{embed '@inc/card.tpl'");
    expect($out)->toContain('{block content}');
    expect($out)->toContain('X');
    expect($out)->toContain('{/embed}');
});

it('supports embed path variants', function () {
    $pp = new CustomTagPreprocessor();

    $out = $pp->preprocess('<embed src="partials/card.tpl"></embed>');
    expect($out)->toContain("{embed 'partials/card.tpl'");

    $out = $pp->preprocess('<embed src="/views/partials/card.tpl"></embed>');
    expect($out)->toContain("{embed '/views/partials/card.tpl'");

    $out = $pp->preprocess('<embed src="@inc/card.tpl"></embed>');
    expect($out)->toContain("{embed '@inc/card.tpl'");
});

it('places layout macro at the beginning', function () {
    $tpl = '<layout src="@layouts/base.tpl"></layout><div>Body</div>';
    $pp = new CustomTagPreprocessor();
    $out = $pp->preprocess($tpl);

    expect(trim($out))->toStartWith("{layout '@layouts/base.tpl'}");
});

it('supports layout path variants', function () {
    $pp = new CustomTagPreprocessor();

    $out = $pp->preprocess('<layout src="layouts/base.tpl"></layout>');
    expect(trim($out))->toStartWith("{layout 'layouts/base.tpl'}");

    $out = $pp->preprocess('<layout src="/views/layouts/base.tpl"></layout>');
    expect(trim($out))->toStartWith("{layout '/views/layouts/base.tpl'}");

    $out = $pp->preprocess('<layout src="@layouts/base.tpl"></layout>');
    expect(trim($out))->toStartWith("{layout '@layouts/base.tpl'}");
});
it('transforms loop tag with empty branch', function () {
    $tpl = '<loop data="$rows" item="row"><div>{$row.title}</div><empty>None</empty></loop>';
    $pp = new CustomTagPreprocessor();
    $out = $pp->preprocess($tpl);

    expect($out)->toContain('{foreach $rows as $row}');
    expect($out)->toContain('{else}');
    expect($out)->toContain('None');
    expect($out)->toContain('{/foreach}');
});

it('transforms if/elseif/else tags', function () {
    $tpl = '<if condition="$a">A<elseif condition="$b">B<else>C</else></elseif></if>';
    $pp = new CustomTagPreprocessor();
    $out = $pp->preprocess($tpl);

    expect($out)->toContain('{if $a}');
    expect($out)->toContain('{elseif $b}');
    expect($out)->toContain('{else}');
    expect($out)->toContain('{/if}');
});

it('transforms for tag with attributes', function () {
    $tpl = '<for from="0" to="3" step="1" as="i">{$i}</for>';
    $pp = new CustomTagPreprocessor();
    $out = $pp->preprocess($tpl);

    expect($out)->toContain('{for $i = 0; $i <= 3; $i += 1}');
    expect($out)->toContain('{/for}');
});

it('transforms foreach tag with key and item', function () {
    $tpl = '<foreach of="$list" as="item" key="idx">{$idx}-{$item}</foreach>';
    $pp = new CustomTagPreprocessor();
    $out = $pp->preprocess($tpl);

    expect($out)->toContain('{foreach $list as $idx => $item}');
    expect($out)->toContain('{/foreach}');
});

it('supports list/info debug and meta parameters', function () {
    $tpl = '<list data="App\\\\Demo\\\\Api\\\\Article::list" meta="m" debug="true"></list>';
    $pp = new CustomTagPreprocessor();
    $out = $pp->preprocess($tpl);

    expect($out)->toContain("'meta' => 'm'");
    expect($out)->toContain("'debug' => true");
});

it('api service returns error for invalid route', function () {
    $api = new ApiService();
    $data = $api->fetch('bad-route');

    expect($data)->toBe([]);
    expect($api->getLastError())->not()->toBeNull();
    expect($api->getLastError()['exception'])->toBe('InvalidRoute');
});

it('api service returns error for missing class', function () {
    $api = new ApiService();
    $data = $api->fetch('Tests\\\\Views\\\\Missing::list');

    expect($data)->toBe([]);
    expect($api->getLastError())->not()->toBeNull();
    expect($api->getLastError()['message'])->toContain('Class not found');
});

it('api service fetches data from class method', function () {
    $api = new ApiService();
    $data = $api->fetch('DemoApiHandler::list');

    expect($data)->toHaveKey('data');
    expect($data)->toHaveKey('meta');
    expect($data['data']->ok ?? null)->toBeTrue();
    expect($data['meta']->page ?? null)->toBe(1);
});

it('api service call supports query array', function () {
    $api = new ApiService();
    $data = $api->call('DemoApiHandler::simple', ['q' => 'x']);

    expect($data)->toHaveKey('data');
    expect($data['data']['q'] ?? null)->toBe('x');
});

it('split returns ArrayObject for array payload', function () {
    $api = new ApiService();
    [$data, $meta] = $api->split([
        'data' => ['ok' => true],
        'meta' => ['page' => 1],
    ]);

    expect($data)->toBeInstanceOf(\ArrayObject::class);
    expect($meta)->toBeInstanceOf(\ArrayObject::class);
    expect($data->ok)->toBeTrue();
    expect($meta->page)->toBe(1);
});
