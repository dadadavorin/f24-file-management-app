<?php

declare(strict_types=1);

use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\ValueObject\NodeName;
use App\Domain\FileSystem\ValueObject\NodePath;
use App\Infrastructure\Persistence\Eloquent\EloquentNodeRepository;
use Illuminate\Support\Facades\DB;

/**
 * path and depth are two representations of one relationship (ADR-0001) that
 * must never diverge — divergence makes subtree search and subtree delete
 * silently wrong, with no error. This rebuilds every node's ancestor chain
 * independently, by walking parent_id alone, and checks it against the
 * stored path and depth.
 */
test('path and depth match their reconstructed ancestor chain across a randomized 4-level tree', function () {
    $repo = new EloquentNodeRepository;
    $root = $repo->findRoot();

    $frontier = [$root];
    $nodeCount = 1;

    for ($level = 1; $level <= 4; $level++) {
        $nextFrontier = [];

        foreach ($frontier as $parent) {
            $childCount = random_int(3, 5);

            // At least two children are folders (when not at the deepest
            // level) so the frontier can never accidentally die out early —
            // the tree must actually reach 4 levels, not just up to 4.
            $indices = range(0, $childCount - 1);
            shuffle($indices);
            $folderIndices = $level < 4 ? array_slice($indices, 0, 2) : [];

            foreach (range(0, $childCount - 1) as $i) {
                $type = in_array($i, $folderIndices, true) ? NodeType::Folder : NodeType::File;
                $name = NodeName::fromString("l{$level}-{$i}-".bin2hex(random_bytes(4)));
                $path = NodePath::forChild($parent->path, $parent->id);

                $node = $repo->create($parent->id, $type, $name, $path);
                $nodeCount++;

                if ($type === NodeType::Folder) {
                    $nextFrontier[] = $node;
                }
            }
        }

        $frontier = $nextFrontier;
    }

    expect($nodeCount)->toBeGreaterThan(30);

    $rows = DB::table('nodes')->get(['id', 'parent_id', 'path', 'depth'])->keyBy('id');

    $reconstructPath = function (object $row) use ($rows, &$reconstructPath): string {
        if ($row->parent_id === null) {
            return '/';
        }

        return $reconstructPath($rows[$row->parent_id]).$row->parent_id.'/';
    };

    foreach ($rows as $row) {
        $expectedPath = $reconstructPath($row);

        expect($row->path)->toBe($expectedPath)
            ->and((int) $row->depth)->toBe(substr_count($expectedPath, '/') - 1);
    }
});
