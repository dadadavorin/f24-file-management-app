<?php

declare(strict_types=1);

namespace App\Domain\FileSystem\Exception;

final class RootIsImmutable extends FileSystemException
{
    public function __construct()
    {
        parent::__construct('The root folder cannot be modified or deleted.');
    }
}
