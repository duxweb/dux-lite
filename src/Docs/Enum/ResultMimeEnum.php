<?php
declare(strict_types=1);

namespace Core\Docs\Enum;

enum ResultMimeEnum: string
{
    case JSON = 'application/json';
    case XML = 'application/xml';
    case TEXT = 'text/plain';
    case HTML = 'text/html';
}
