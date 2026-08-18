<?php

declare(strict_types=1);

namespace App\Application\FileSystem;

use App\Domain\FileSystem\Dto\NodeData;
use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\Exception\NodeNotFound;
use App\Domain\FileSystem\Exception\ParentIsNotAFolder;
use App\Domain\FileSystem\Repository\NodeRepository;
use App\Domain\FileSystem\ValueObject\NodeName;
use App\Domain\FileSystem\ValueObject\NodePath;

final readonly class CreateNode
{
    public function __construct(private NodeRepository $repository) {}

    public function execute(int $parentId, NodeType $type, string $name): NodeData
    {
        $nodeName = NodeName::fromString($name);

        $parent = $this->repository->find($parentId) ?? throw new NodeNotFound($parentId);

        if ($parent->type !== NodeType::Folder) {
            throw new ParentIsNotAFolder($parentId);
        }

        $path = NodePath::forChild($parent->path, $parent->id);

        return $this->repository->create($parentId, $type, $nodeName, $path);
    }
}
