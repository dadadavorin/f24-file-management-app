<?php

declare(strict_types=1);

namespace App\Application\FileSystem;

use App\Domain\FileSystem\Exception\NodeNotFound;
use App\Domain\FileSystem\Exception\RootIsImmutable;
use App\Domain\FileSystem\Repository\NodeRepository;

final readonly class DeleteNode
{
    public function __construct(private NodeRepository $repository) {}

    public function execute(int $nodeId): void
    {
        $node = $this->repository->find($nodeId) ?? throw new NodeNotFound($nodeId);

        if ($node->parentId === null) {
            throw new RootIsImmutable;
        }

        // Descendants first, then the node itself, in one transaction — see
        // EloquentNodeRepository::deleteSubtree for why that order matters.
        $this->repository->deleteSubtree($node);
    }
}
