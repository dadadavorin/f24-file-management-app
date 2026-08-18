<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * NFR-1: the prefix-suggestion query must be served by an index range scan
 * with no sort step, and the subtree delete must be served by `nodes_path`
 * rather than a sequential scan. Below a few hundred rows Postgres correctly
 * prefers a sequential scan, so this seeds ~50k rows and runs ANALYZE first —
 * a smaller fixture would pass against a schema that has already regressed.
 */
const SEED_FILE_COUNT = 50_000;

function seedManyFilesUnder(int $parentId, string $parentPath, int $parentDepth, string $namePrefix): void
{
    $now = now();
    $childPath = $parentPath.$parentId.'/';
    $childDepth = $parentDepth + 1;

    $rows = [];

    for ($i = 0; $i < SEED_FILE_COUNT; $i++) {
        $rows[] = [
            'parent_id' => $parentId,
            'type' => 'file',
            'name' => sprintf('%s-%06d.txt', $namePrefix, $i),
            'path' => $childPath,
            'depth' => $childDepth,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    foreach (array_chunk($rows, 5000) as $chunk) {
        DB::table('nodes')->insert($chunk);
    }

    DB::statement('ANALYZE nodes');
}

/**
 * @return array<int, array<string, mixed>>
 */
function explainPlanNodes(string $sql, array $bindings): array
{
    $result = DB::select("EXPLAIN (FORMAT JSON) {$sql}", $bindings);
    $plan = json_decode((string) $result[0]->{'QUERY PLAN'}, true, flags: JSON_THROW_ON_ERROR)[0]['Plan'];

    $flatten = function (array $node) use (&$flatten): array {
        $nodes = [$node];

        foreach ($node['Plans'] ?? [] as $child) {
            $nodes = [...$nodes, ...$flatten($child)];
        }

        return $nodes;
    };

    return $flatten($plan);
}

test('the prefix-suggestion query is an index scan on nodes_file_name with no sort and no seq scan', function () {
    $root = DB::table('nodes')->whereNull('parent_id')->first(['id', 'path', 'depth']);

    seedManyFilesUnder($root->id, $root->path, $root->depth, 'file');

    $nodes = explainPlanNodes(
        <<<'SQL'
        select id, parent_id, type, name, path, depth, created_at, updated_at
          from nodes
         where type = ?
           and lower(name) COLLATE "C" LIKE ? ESCAPE '\'
         order by lower(name) COLLATE "C" asc
         limit ?
        SQL,
        ['file', 'file-%', 10],
    );

    $indexNames = array_filter(array_column($nodes, 'Index Name'));
    $nodeTypes = array_column($nodes, 'Node Type');
    $seqScansOnNodes = array_filter(
        $nodes,
        fn (array $node): bool => ($node['Node Type'] ?? null) === 'Seq Scan' && ($node['Relation Name'] ?? null) === 'nodes',
    );

    expect($indexNames)->toContain('nodes_file_name')
        ->and($nodeTypes)->not->toContain('Sort')
        ->and($seqScansOnNodes)->toBe([]);
});

test('the subtree delete is an index scan on nodes_path with no seq scan', function () {
    $root = DB::table('nodes')->whereNull('parent_id')->first(['id', 'path', 'depth']);

    // Most of the table lives outside the deleted subtree, so the deleted
    // folder is a small, selective slice — exactly the case a sequential
    // scan across every row would be wasteful for.
    seedManyFilesUnder($root->id, $root->path, $root->depth, 'noise');

    $folderId = DB::table('nodes')->insertGetId([
        'parent_id' => $root->id,
        'type' => 'folder',
        'name' => 'big',
        'path' => $root->path.$root->id.'/',
        'depth' => $root->depth + 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $folderPath = $root->path.$root->id.'/';
    $now = now();

    DB::table('nodes')->insert(array_map(
        fn (int $i): array => [
            'parent_id' => $folderId,
            'type' => 'file',
            'name' => sprintf('deleted-%04d.txt', $i),
            'path' => $folderPath.$folderId.'/',
            'depth' => $root->depth + 2,
            'created_at' => $now,
            'updated_at' => $now,
        ],
        range(0, 199),
    ));

    DB::statement('ANALYZE nodes');

    $subtreePattern = $folderPath.$folderId.'/%';

    $nodes = explainPlanNodes(
        'delete from nodes where path COLLATE "C" LIKE ?',
        [$subtreePattern],
    );

    $indexNames = array_filter(array_column($nodes, 'Index Name'));
    $seqScansOnNodes = array_filter(
        $nodes,
        fn (array $node): bool => ($node['Node Type'] ?? null) === 'Seq Scan' && ($node['Relation Name'] ?? null) === 'nodes',
    );

    expect($indexNames)->toContain('nodes_path')
        ->and($seqScansOnNodes)->toBe([]);
});
