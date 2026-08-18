<?php

declare(strict_types=1);

namespace App\Domain\FileSystem\Exception;

final class DuplicateNodeName extends FileSystemException
{
    public function __construct(public readonly string $name, public readonly int $parentId)
    {
        parent::__construct("A node named \"{$name}\" already exists in this folder.");
    }
}
