<?php
declare(strict_types=1);

namespace Core\Docs;

class Docs
{
    public function build(string $host = 'localhost', string $port = '8080', ?string $version = null): string
    {
        $command = new DocsCommand();
        return $command->build($host, $port, $version);
    }
}
