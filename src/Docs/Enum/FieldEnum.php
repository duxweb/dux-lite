<?php
declare(strict_types=1);

namespace Core\Docs\Enum;

enum FieldEnum: string
{
    case INT = 'integer';
    case STRING = 'string';
    case FLOAT = 'number';
    case JSON = 'json';
    case BOOL = 'boolean';
    case ARRAY = 'array';
    case OBJECT = 'object';
    case NULL = 'null';

}
