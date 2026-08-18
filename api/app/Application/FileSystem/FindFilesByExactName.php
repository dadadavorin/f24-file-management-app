<?php

declare(strict_types=1);

namespace App\Application\FileSystem;

use App\Domain\FileSystem\Dto\Cursor;
use App\Domain\FileSystem\Dto\NodeData;
use App\Domain\FileSystem\Dto\NodePage;
use App\Domain\FileSystem\Repository\NodeRepository;
use App\Domain\FileSystem\ValueObject\NodeName;

final readonly class FindFilesByExactName
{
    public function __construct(private NodeRepository $repository) {}

    /**
     * @return array{page: NodePage, folders: array<int, NodeData>}
     */
    public function execute(string $name, ?int $subtreeRootId, ?Cursor $cursor, int $limit): array
    {
        $nodeName = NodeName::fromString($name);

        $page = $this->repository->findFilesByName($nodeName, $subtreeRootId, $cursor, $limit);

        return [
            'page' => $page,
            'folders' => $this->resolveContainingFolders($page),
        ];
    }

    /**
     * Every ancestor id across every result, collected up front so the
     * containing-folder names are resolved with one whereIn instead of one
     * query per result.
     *
     * @return array<int, NodeData>
     */
    private function resolveContainingFolders(NodePage $page): array
    {
        $ancestorIds = [];

        foreach ($page->items as $item) {
            foreach ($item->path->ancestorIds() as $ancestorId) {
                $ancestorIds[$ancestorId] = true;
            }
        }

        $folders = [];

        foreach ($this->repository->findByIds(array_keys($ancestorIds)) as $folder) {
            $folders[$folder->id] = $folder;
        }

        return $folders;
    }
}
