<?php

declare(strict_types=1);

namespace App\Domain\FileSystem\Repository;

use App\Domain\FileSystem\Dto\Cursor;
use App\Domain\FileSystem\Dto\NodeData;
use App\Domain\FileSystem\Dto\NodePage;
use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\Exception\DuplicateNodeName;
use App\Domain\FileSystem\ValueObject\NodeName;
use App\Domain\FileSystem\ValueObject\NodePath;

interface NodeRepository
{
    public function find(int $id): ?NodeData;

    public function findRoot(): NodeData;

    /**
     * @param  int[]  $ids
     * @return NodeData[]
     */
    public function findByIds(array $ids): array;

    public function children(int $parentId, ?Cursor $cursor, int $limit): NodePage;

    /**
     * @param  int[]  $folderIds
     * @return array<int, int>
     */
    public function childCounts(array $folderIds, int $cap): array;

    /**
     * @throws DuplicateNodeName
     */
    public function create(int $parentId, NodeType $type, NodeName $name, NodePath $path): NodeData;

    public function deleteSubtree(NodeData $node): void;

    public function findFilesByName(NodeName $name, ?int $subtreeRootId, ?Cursor $cursor, int $limit): NodePage;

    /**
     * @return NodeData[]
     */
    public function suggestFilesByPrefix(string $escapedPrefix, ?int $subtreeRootId, int $limit): array;
}
