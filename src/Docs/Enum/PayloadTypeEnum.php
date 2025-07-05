<?php
declare(strict_types=1);

namespace Core\Docs\Enum;

enum PayloadTypeEnum: string
{
    case JSON = 'json';
    case FORM = 'form';
    case XML = 'xml';
    case STRING = 'string';
    case FILE = 'file';
    case MULTIPART = 'multipart';
    case RAW = 'raw';


    public function mime(): string
    {
        return match ($this) {
            $this::JSON => 'application/json',
            $this::FORM => 'application/x-www-form-urlencoded',
            $this::XML => 'application/xml',
            $this::MULTIPART => 'multipart/form-data',
            $this::FILE => 'application/octet-stream',
            $this::STRING => 'text/plain',
            $this::RAW => 'application/octet-stream',
            default => 'application/json'
        };
    }
}


