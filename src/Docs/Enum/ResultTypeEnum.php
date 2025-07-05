<?php
declare(strict_types=1);

namespace Core\Docs\Enum;

enum ResultTypeEnum: string
{
    case JSON = 'json';
    case XML = 'xml';
    case TEXT = 'text';
    case HTML = 'html';
    case PLAIN = 'plain';
    case BINARY = 'binary';
    case EVENT_STREAM = 'event_stream';
    case MESSAGE = 'message';
}
