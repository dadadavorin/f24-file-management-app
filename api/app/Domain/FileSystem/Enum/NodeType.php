<?php

declare(strict_types=1);

namespace App\Domain\FileSystem\Enum;

enum NodeType: string
{
    case Folder = 'folder';
    case File = 'file';
}
