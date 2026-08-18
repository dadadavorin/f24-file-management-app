<?php

declare(strict_types=1);

namespace App\Application\FileSystem;

use App\Domain\FileSystem\Dto\Cursor;
use App\Domain\FileSystem\Dto\NodeData;
use App\Domain\FileSystem\Dto\NodePage;
use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\Exception\NodeNotFound;
use App\Domain\FileSystem\Exception\ParentIsNotAFolder;
use App\Domain\FileSystem\Repository\NodeRepository;

final readonly class ListChildren
{
    private const int CHILD_COUNT_CAP = 100;

    public function __construct(private NodeRepository $repository) {}

    /**
     * @return array{page: NodePage, childCounts: array<int, int>}
     */
    public function execute(int $folderId, ?Cursor $cursor, int $limit): array
    {
        $folder = $this->repository->find($folderId) ?? throw new NodeNotFound($folderId);

        if ($folder->type !== NodeType::Folder) {
            throw new ParentIsNotAFolder($folderId);
        }

        $page = $this->repository->children($folderId, $cursor, $limit);

        $folderIds = array_values(array_map(
            fn (NodeData $node): int => $node->id,
            array_filter($page->items, fn (NodeData $node): bool => $node->type === NodeType::Folder),
        ));

        return [
            'page' => $page,
            'childCounts' => $this->repository->childCounts($folderIds, self::CHILD_COUNT_CAP),
        ];
    }
}
