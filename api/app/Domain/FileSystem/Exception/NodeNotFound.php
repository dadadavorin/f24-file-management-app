<?php

declare(strict_types=1);

namespace App\Domain\FileSystem\Exception;

final class NodeNotFound extends FileSystemException
{
    public function __construct(public readonly int $nodeId)
    {
        parent::__construct("Node {$nodeId} was not found.");
    }
}
