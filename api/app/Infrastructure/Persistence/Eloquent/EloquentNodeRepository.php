<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\FileSystem\Dto\Cursor;
use App\Domain\FileSystem\Dto\NodeData;
use App\Domain\FileSystem\Dto\NodePage;
use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\Exception\DuplicateNodeName;
use App\Domain\FileSystem\Repository\NodeRepository;
use App\Domain\FileSystem\ValueObject\NodeName;
use App\Domain\FileSystem\ValueObject\NodePath;
use App\Infrastructure\Persistence\Eloquent\Models\Node;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentNodeRepository implements NodeRepository
{
    public function find(int $id): ?NodeData
    {
        $node = Node::query()->find($id);

        return $node === null ? null : $this->toData($node);
    }

    public function findRoot(): NodeData
    {
        return $this->toData(Node::query()->whereNull('parent_id')->firstOrFail());
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Node::query()->whereIn('id', $ids)->get()
            ->map($this->toData(...))
            ->all();
    }

    public function children(int $parentId, ?Cursor $cursor, int $limit): NodePage
    {
        $query = Node::query()
            ->selectRaw('*, lower(name) as lower_name')
            ->where('parent_id', $parentId)
            ->orderByRaw('sort_rank asc, lower(name) COLLATE "C" asc, id asc');

        $this->applyCursor($query, $cursor);

        return $this->paginate($query->limit($limit + 1)->get(), $limit);
    }

    public function childCounts(array $folderIds, int $cap): array
    {
        if ($folderIds === []) {
            return [];
        }

        // A folder's own child count is bounded by scanning at most $cap rows
        // per folder (a LATERAL join with its own LIMIT), not by counting the
        // whole set and clamping the result — otherwise a folder with a huge
        // number of children would cost as much as listing it in full.
        $rows = DB::select(
            <<<'SQL'
            select f.id as parent_id, count(*) as child_count
              from unnest(?::bigint[]) as f(id)
              cross join lateral (
                  select 1 from nodes where nodes.parent_id = f.id limit ?
              ) as capped
             group by f.id
            SQL,
            ['{'.implode(',', $folderIds).'}', $cap],
        );

        $counts = [];

        foreach ($rows as $row) {
            if (! is_object($row)) {
                continue;
            }

            $data = get_object_vars($row);

            if (! is_numeric($data['parent_id']) || ! is_numeric($data['child_count'])) {
                continue;
            }

            $counts[(int) $data['parent_id']] = (int) $data['child_count'];
        }

        return $counts;
    }

    public function create(int $parentId, NodeType $type, NodeName $name, NodePath $path): NodeData
    {
        try {
            $node = Node::query()->create([
                'parent_id' => $parentId,
                'type' => $type->value,
                'name' => $name->value,
                'path' => $path->value,
                'depth' => $path->depth,
            ]);
        } catch (QueryException $exception) {
            // SQLSTATE 23505: unique_violation. The only place allowed to know
            // this code — everywhere else sees DuplicateNodeName.
            if ($exception->getCode() === '23505') {
                throw new DuplicateNodeName($name->value, $parentId);
            }

            throw $exception;
        }

        return $this->toData($node);
    }

    public function deleteSubtree(NodeData $node): void
    {
        // One transaction, two statements: the descendants first, then the
        // node itself, so a failure never leaves an orphaned subtree.
        DB::transaction(function () use ($node): void {
            DB::table('nodes')
                ->whereRaw('path COLLATE "C" LIKE ?', [$node->path->subtreePattern($node->id)])
                ->delete();

            DB::table('nodes')->where('id', $node->id)->delete();
        });
    }

    public function findFilesByName(NodeName $name, ?int $subtreeRootId, ?Cursor $cursor, int $limit): NodePage
    {
        $subtreePattern = $this->subtreePatternFor($subtreeRootId);

        if ($subtreeRootId !== null && $subtreePattern === null) {
            return new NodePage([], null);
        }

        $query = Node::query()
            ->selectRaw('*, lower(name) as lower_name')
            ->where('type', NodeType::File->value)
            ->whereRaw('lower(name) COLLATE "C" = ?', [mb_strtolower($name->value)])
            ->orderByRaw('sort_rank asc, lower(name) COLLATE "C" asc, id asc');

        if ($subtreePattern !== null) {
            $query->whereRaw('path COLLATE "C" LIKE ?', [$subtreePattern]);
        }

        $this->applyCursor($query, $cursor);

        return $this->paginate($query->limit($limit + 1)->get(), $limit);
    }

    public function suggestFilesByPrefix(string $escapedPrefix, ?int $subtreeRootId, int $limit): array
    {
        $subtreePattern = $this->subtreePatternFor($subtreeRootId);

        if ($subtreeRootId !== null && $subtreePattern === null) {
            return [];
        }

        $query = Node::query()
            ->where('type', NodeType::File->value)
            ->whereRaw(<<<'SQL'
                lower(name) COLLATE "C" LIKE ? ESCAPE '\'
                SQL, [mb_strtolower($escapedPrefix).'%'])
            ->orderByRaw('lower(name) COLLATE "C" asc');

        if ($subtreePattern !== null) {
            $query->whereRaw('path COLLATE "C" LIKE ?', [$subtreePattern]);
        }

        return $query->limit($limit)->get()->map($this->toData(...))->all();
    }

    private function subtreePatternFor(?int $subtreeRootId): ?string
    {
        if ($subtreeRootId === null) {
            return null;
        }

        $root = Node::query()->select(['id', 'path'])->find($subtreeRootId);

        return $root === null ? null : $root->path.$root->id.'/%';
    }

    /**
     * @param  Builder<Node>  $query
     */
    private function applyCursor(Builder $query, ?Cursor $cursor): void
    {
        if ($cursor === null) {
            return;
        }

        $query->whereRaw(
            '(sort_rank, lower(name) COLLATE "C", id) > (?, ?, ?)',
            [$cursor->sortRank, $cursor->lowerName, $cursor->id],
        );
    }

    /**
     * @param  Collection<int, Node>  $rows
     */
    private function paginate(Collection $rows, int $limit): NodePage
    {
        $hasMore = $rows->count() > $limit;
        $items = $hasMore ? $rows->take($limit) : $rows;

        $nextCursor = null;

        if ($hasMore) {
            $last = $items->last() ?? throw new \LogicException('Expected at least one row when hasMore is true.');
            $lowerName = $last->getAttribute('lower_name');
            $lowerName = is_string($lowerName) ? $lowerName : throw new \LogicException('lower_name was not selected.');
            $nextCursor = new Cursor($last->sort_rank, $lowerName, $last->id);
        }

        return new NodePage($items->map($this->toData(...))->values()->all(), $nextCursor);
    }

    private function toData(Node $node): NodeData
    {
        return new NodeData(
            id: $node->id,
            parentId: $node->parent_id,
            type: NodeType::from($node->type),
            name: $node->name,
            path: NodePath::fromStored($node->path, $node->depth),
            createdAt: \DateTimeImmutable::createFromInterface($node->created_at),
            updatedAt: \DateTimeImmutable::createFromInterface($node->updated_at),
        );
    }
}
