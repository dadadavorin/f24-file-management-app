<?php

declare(strict_types=1);

namespace App\Domain\FileSystem\Exception;

final class MaxDepthExceeded extends FileSystemException
{
    public function __construct(public readonly int $maxDepth)
    {
        parent::__construct("Folders cannot be nested more than {$maxDepth} deep.");
    }
}
