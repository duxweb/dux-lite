<?php

declare(strict_types=1);

namespace Core\Context;

use Psr\Http\Message\ServerRequestInterface;

class RequestContext
{
    private ?ServerRequestInterface $request = null;
    private ?string $lang = null;

    public function setRequest(?ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function request(): ?ServerRequestInterface
    {
        return $this->request;
    }

    public function setLang(?string $lang): void
    {
        $this->lang = $lang;
    }

    public function lang(): ?string
    {
        if ($this->lang) {
            return $this->lang;
        }

        $lang = $this->request?->getAttribute('lang');
        return is_string($lang) && $lang !== '' ? $lang : null;
    }

    public function clear(): void
    {
        $this->request = null;
        $this->lang = null;
    }
}
