<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Domain\FileSystem\Dto\Cursor;
use App\Domain\FileSystem\Dto\NodeData;
use App\Domain\FileSystem\Dto\NodePage;
use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\Exception\DuplicateNodeName;
use App\Domain\FileSystem\Repository\NodeRepository;
use App\Domain\FileSystem\ValueObject\NodeName;
use App\Domain\FileSystem\ValueObject\NodePath;

/**
 * Mirrors EloquentNodeRepository's observable behavior without a database, so
 * the Application-layer unit suite runs fast. Its own correctness against the
 * real implementation is what the repository contract suite exists to check.
 */
final class InMemoryNodeRepository implements NodeRepository
{
    /** @var array<int, NodeData> */
    private array $nodes = [];

    private int $nextId = 1;

    public function __construct()
    {
        $rootId = $this->nextId++;
        $now = new \DateTimeImmutable;

        $this->nodes[$rootId] = new NodeData(
            id: $rootId,
            parentId: null,
            type: NodeType::Folder,
            name: 'Root',
            path: NodePath::forRoot(),
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function find(int $id): ?NodeData
    {
        return $this->nodes[$id] ?? null;
    }

    public function findRoot(): NodeData
    {
        foreach ($this->nodes as $node) {
            if ($node->parentId === null) {
                return $node;
            }
        }

        throw new \RuntimeException('No root node exists.');
    }

    public function findByIds(array $ids): array
    {
        $found = [];

        foreach ($ids as $id) {
            if (isset($this->nodes[$id])) {
                $found[] = $this->nodes[$id];
            }
        }

        return $found;
    }

    public function children(int $parentId, ?Cursor $cursor, int $limit): NodePage
    {
        $children = array_values(array_filter(
            $this->nodes,
            fn (NodeData $node): bool => $node->parentId === $parentId,
        ));

        usort($children, $this->compareByListingOrder(...));

        return $this->paginate($children, $cursor, $limit);
    }

    public function childCounts(array $folderIds, int $cap): array
    {
        $counts = [];

        foreach ($folderIds as $folderId) {
            $count = 0;

            foreach ($this->nodes as $node) {
                if ($node->parentId === $folderId) {
                    $count++;

                    if ($count >= $cap) {
                        break;
                    }
                }
            }

            if ($count > 0) {
                $counts[$folderId] = $count;
            }
        }

        return $counts;
    }

    public function create(int $parentId, NodeType $type, NodeName $name, NodePath $path): NodeData
    {
        foreach ($this->nodes as $node) {
            if ($node->parentId === $parentId && mb_strtolower($node->name) === mb_strtolower($name->value)) {
                throw new DuplicateNodeName($name->value, $parentId);
            }
        }

        $id = $this->nextId++;
        $now = new \DateTimeImmutable;

        $node = new NodeData(
            id: $id,
            parentId: $parentId,
            type: $type,
            name: $name->value,
            path: $path,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->nodes[$id] = $node;

        return $node;
    }

    public function deleteSubtree(NodeData $node): void
    {
        $prefix = rtrim($node->path->subtreePattern($node->id), '%');

        foreach ($this->nodes as $id => $candidate) {
            if (str_starts_with($candidate->path->value, $prefix)) {
                unset($this->nodes[$id]);
            }
        }

        unset($this->nodes[$node->id]);
    }

    public function findFilesByName(NodeName $name, ?int $subtreeRootId, ?Cursor $cursor, int $limit): NodePage
    {
        $subtreePrefix = $this->subtreePrefixFor($subtreeRootId);

        if ($subtreeRootId !== null && $subtreePrefix === null) {
            return new NodePage([], null);
        }

        $needle = mb_strtolower($name->value);

        $matches = array_values(array_filter($this->nodes, function (NodeData $node) use ($needle, $subtreePrefix): bool {
            if ($node->type !== NodeType::File) {
                return false;
            }

            if (mb_strtolower($node->name) !== $needle) {
                return false;
            }

            return $subtreePrefix === null || str_starts_with($node->path->value, $subtreePrefix);
        }));

        usort($matches, $this->compareByListingOrder(...));

        return $this->paginate($matches, $cursor, $limit);
    }

    public function suggestFilesByPrefix(string $escapedPrefix, ?int $subtreeRootId, int $limit): array
    {
        $subtreePrefix = $this->subtreePrefixFor($subtreeRootId);

        if ($subtreeRootId !== null && $subtreePrefix === null) {
            return [];
        }

        $needle = mb_strtolower($this->unescapeLikePattern($escapedPrefix));

        $matches = array_values(array_filter($this->nodes, function (NodeData $node) use ($needle, $subtreePrefix): bool {
            if ($node->type !== NodeType::File) {
                return false;
            }

            if (! str_starts_with(mb_strtolower($node->name), $needle)) {
                return false;
            }

            return $subtreePrefix === null || str_starts_with($node->path->value, $subtreePrefix);
        }));

        usort($matches, fn (NodeData $a, NodeData $b): int => mb_strtolower($a->name) <=> mb_strtolower($b->name));

        return array_slice($matches, 0, $limit);
    }

    private function subtreePrefixFor(?int $subtreeRootId): ?string
    {
        if ($subtreeRootId === null) {
            return null;
        }

        $root = $this->nodes[$subtreeRootId] ?? null;

        return $root === null ? null : rtrim($root->path->subtreePattern($root->id), '%');
    }

    /**
     * Reverses the \, %, _ escaping the caller applies before a LIKE query,
     * so the fake matches Postgres's `LIKE ... ESCAPE '\'` semantics.
     */
    private function unescapeLikePattern(string $escaped): string
    {
        $result = '';
        $length = strlen($escaped);

        for ($i = 0; $i < $length; $i++) {
            if ($escaped[$i] === '\\' && $i + 1 < $length) {
                $i++;
            }

            $result .= $escaped[$i];
        }

        return $result;
    }

    private function compareByListingOrder(NodeData $a, NodeData $b): int
    {
        return $this->sortRankOf($a) <=> $this->sortRankOf($b)
            ?: mb_strtolower($a->name) <=> mb_strtolower($b->name)
            ?: $a->id <=> $b->id;
    }

    private function sortRankOf(NodeData $node): int
    {
        return $node->type === NodeType::Folder ? 0 : 1;
    }

    /**
     * @param  NodeData[]  $sorted  Already ordered by (sort_rank, lower(name), id).
     */
    private function paginate(array $sorted, ?Cursor $cursor, int $limit): NodePage
    {
        if ($cursor !== null) {
            $sorted = array_values(array_filter(
                $sorted,
                fn (NodeData $node): bool => $this->isAfterCursor($node, $cursor),
            ));
        }

        $hasMore = count($sorted) > $limit;
        $page = array_slice($sorted, 0, $limit);

        $nextCursor = null;

        if ($hasMore) {
            $last = $page[count($page) - 1];
            $nextCursor = new Cursor($this->sortRankOf($last), mb_strtolower($last->name), $last->id);
        }

        return new NodePage($page, $nextCursor);
    }

    private function isAfterCursor(NodeData $node, Cursor $cursor): bool
    {
        $comparison = $this->sortRankOf($node) <=> $cursor->sortRank
            ?: mb_strtolower($node->name) <=> $cursor->lowerName
            ?: $node->id <=> $cursor->id;

        return $comparison > 0;
    }
}
