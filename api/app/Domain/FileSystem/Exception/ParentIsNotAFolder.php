<?php

declare(strict_types=1);

namespace App\Domain\FileSystem\Exception;

final class ParentIsNotAFolder extends FileSystemException
{
    public function __construct(public readonly int $parentId)
    {
        parent::__construct("Node {$parentId} is not a folder.");
    }
}
