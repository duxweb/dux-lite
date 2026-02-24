<?php

declare(strict_types=1);

use Core\Utils\RequestParam;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;

it('fills query and body defaults', function (): void {
    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/demo')
        ->withQueryParams(['keyword' => 'abc'])
        ->withParsedBody(['page' => 2]);

    $query = RequestParam::query($request, ['keyword' => '', 'status' => 1]);
    $body = RequestParam::body($request, ['page' => 1, 'limit' => 20]);

    expect($query)->toBe(['keyword' => 'abc', 'status' => 1]);
    expect($body)->toBe(['page' => 2, 'limit' => 20]);
});

it('returns uploaded files by path', function (): void {
    $tmp1 = tempnam(sys_get_temp_dir(), 'dux-file-');
    $tmp2 = tempnam(sys_get_temp_dir(), 'dux-file-');
    file_put_contents($tmp1, 'avatar');
    file_put_contents($tmp2, 'gallery');

    $avatar = new UploadedFile($tmp1, 'avatar.jpg', 'image/jpeg', filesize($tmp1));
    $gallery = new UploadedFile($tmp2, 'gallery.jpg', 'image/jpeg', filesize($tmp2));

    $request = (new ServerRequestFactory())
        ->createServerRequest('POST', '/upload')
        ->withUploadedFiles([
            'avatar' => $avatar,
            'images' => [
                'gallery' => [$gallery],
            ],
        ]);

    expect(RequestParam::file($request, 'avatar'))->toBe($avatar);
    expect(RequestParam::file($request, 'images.gallery.0'))->toBe($gallery);
    expect(RequestParam::file($request, ['images', 'gallery', 0]))->toBe($gallery);
    expect(RequestParam::file($request, 'images.cover', 'default'))->toBe('default');
});

