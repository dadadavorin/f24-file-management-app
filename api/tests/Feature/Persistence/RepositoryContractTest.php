<?php

declare(strict_types=1);

use App\Domain\FileSystem\Dto\NodeData;
use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\Exception\DuplicateNodeName;
use App\Domain\FileSystem\Repository\NodeRepository;
use App\Domain\FileSystem\ValueObject\NodeName;
use App\Domain\FileSystem\ValueObject\NodePath;
use App\Infrastructure\Persistence\Eloquent\EloquentNodeRepository;
use Tests\Fakes\InMemoryNodeRepository;

/**
 * Defines NodeRepository's behavior once and runs it against both
 * implementations. Without this, the in-memory fake can drift from
 * EloquentNodeRepository and the Application-layer unit suite (which only
 * ever talks to the fake) would pass while describing behavior the real
 * system does not have.
 */
dataset('repositories', [
    'eloquent' => fn (): NodeRepository => new EloquentNodeRepository,
    'in-memory' => fn (): NodeRepository => new InMemoryNodeRepository,
]);

/**
 * @return string[]
 */
function names(NodeData ...$nodes): array
{
    return array_map(fn (NodeData $node): string => $node->name, $nodes);
}

test('the root node exists and has no parent', function (NodeRepository $repo) {
    $root = $repo->findRoot();

    expect($root->parentId)->toBeNull()
        ->and($root->type)->toBe(NodeType::Folder)
        ->and($root->path->value)->toBe('/')
        ->and($root->path->depth)->toBe(0);
})->with('repositories');

test('a created node round-trips through find', function (NodeRepository $repo) {
    $root = $repo->findRoot();

    $created = $repo->create(
        $root->id,
        NodeType::Folder,
        NodeName::fromString('Documents'),
        NodePath::forChild($root->path, $root->id),
    );

    $found = $repo->find($created->id);

    expect($found)->not->toBeNull()
        ->and($found->name)->toBe('Documents')
        ->and($found->parentId)->toBe($root->id)
        ->and($found->path->value)->toBe($root->path->value.$root->id.'/');
})->with('repositories');

test('find returns null for a missing id', function (NodeRepository $repo) {
    expect($repo->find(999999))->toBeNull();
})->with('repositories');

test('findByIds returns only the nodes that exist', function (NodeRepository $repo) {
    $root = $repo->findRoot();
    $documents = $repo->create($root->id, NodeType::Folder, NodeName::fromString('Documents'), NodePath::forChild($root->path, $root->id));

    $found = $repo->findByIds([$documents->id, 999999]);

    expect($found)->toHaveCount(1)
        ->and($found[0]->id)->toBe($documents->id);
})->with('repositories');

test('creating a duplicate name in the same parent throws DuplicateNodeName', function (NodeRepository $repo) {
    $root = $repo->findRoot();

    $repo->create($root->id, NodeType::Folder, NodeName::fromString('Documents'), NodePath::forChild($root->path, $root->id));

    expect(fn () => $repo->create($root->id, NodeType::Folder, NodeName::fromString('documents'), NodePath::forChild($root->path, $root->id)))
        ->toThrow(DuplicateNodeName::class);
})->with('repositories');

test('the same name is allowed in two different parents', function (NodeRepository $repo) {
    $root = $repo->findRoot();

    $a = $repo->create($root->id, NodeType::Folder, NodeName::fromString('A'), NodePath::forChild($root->path, $root->id));
    $b = $repo->create($root->id, NodeType::Folder, NodeName::fromString('B'), NodePath::forChild($root->path, $root->id));

    $fileInA = $repo->create($a->id, NodeType::File, NodeName::fromString('notes.txt'), NodePath::forChild($a->path, $a->id));
    $fileInB = $repo->create($b->id, NodeType::File, NodeName::fromString('notes.txt'), NodePath::forChild($b->path, $b->id));

    expect($fileInA->id)->not->toBe($fileInB->id);
})->with('repositories');

test('children are listed folders-first then alphabetically', function (NodeRepository $repo) {
    $root = $repo->findRoot();

    $repo->create($root->id, NodeType::File, NodeName::fromString('b.txt'), NodePath::forChild($root->path, $root->id));
    $repo->create($root->id, NodeType::Folder, NodeName::fromString('Zebra'), NodePath::forChild($root->path, $root->id));
    $repo->create($root->id, NodeType::File, NodeName::fromString('a.txt'), NodePath::forChild($root->path, $root->id));
    $repo->create($root->id, NodeType::Folder, NodeName::fromString('Apple'), NodePath::forChild($root->path, $root->id));

    $page = $repo->children($root->id, null, 10);

    expect(names(...$page->items))->toBe(['Apple', 'Zebra', 'a.txt', 'b.txt']);
})->with('repositories');

test('children pagination is exact across a page boundary', function (NodeRepository $repo) {
    $root = $repo->findRoot();

    foreach (['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo'] as $name) {
        $repo->create($root->id, NodeType::Folder, NodeName::fromString($name), NodePath::forChild($root->path, $root->id));
    }

    $firstPage = $repo->children($root->id, null, 2);
    expect(names(...$firstPage->items))->toBe(['Alpha', 'Bravo'])
        ->and($firstPage->nextCursor)->not->toBeNull();

    $secondPage = $repo->children($root->id, $firstPage->nextCursor, 2);
    expect(names(...$secondPage->items))->toBe(['Charlie', 'Delta'])
        ->and($secondPage->nextCursor)->not->toBeNull();

    $thirdPage = $repo->children($root->id, $secondPage->nextCursor, 2);
    expect(names(...$thirdPage->items))->toBe(['Echo'])
        ->and($thirdPage->nextCursor)->toBeNull();
})->with('repositories');

test('children pagination keeps folders before files across a page boundary', function (NodeRepository $repo) {
    $root = $repo->findRoot();

    $repo->create($root->id, NodeType::Folder, NodeName::fromString('Zebra'), NodePath::forChild($root->path, $root->id));
    $repo->create($root->id, NodeType::Folder, NodeName::fromString('Apple'), NodePath::forChild($root->path, $root->id));
    $repo->create($root->id, NodeType::File, NodeName::fromString('a.txt'), NodePath::forChild($root->path, $root->id));
    $repo->create($root->id, NodeType::File, NodeName::fromString('z.txt'), NodePath::forChild($root->path, $root->id));

    $firstPage = $repo->children($root->id, null, 3);
    expect(names(...$firstPage->items))->toBe(['Apple', 'Zebra', 'a.txt']);

    $secondPage = $repo->children($root->id, $firstPage->nextCursor, 3);
    expect(names(...$secondPage->items))->toBe(['z.txt'])
        ->and($secondPage->nextCursor)->toBeNull();
})->with('repositories');

test('child counts are computed in one call and capped', function (NodeRepository $repo) {
    $root = $repo->findRoot();
    $big = $repo->create($root->id, NodeType::Folder, NodeName::fromString('Big'), NodePath::forChild($root->path, $root->id));
    $empty = $repo->create($root->id, NodeType::Folder, NodeName::fromString('Empty'), NodePath::forChild($root->path, $root->id));

    foreach (range(0, 4) as $i) {
        $repo->create($big->id, NodeType::File, NodeName::fromString("file{$i}.txt"), NodePath::forChild($big->path, $big->id));
    }

    $counts = $repo->childCounts([$big->id, $empty->id], cap: 3);

    expect($counts[$big->id])->toBe(3)
        ->and($counts)->not->toHaveKey($empty->id);
})->with('repositories');

test('deleting a folder removes its whole subtree and leaves siblings intact', function (NodeRepository $repo) {
    $root = $repo->findRoot();
    $documents = $repo->create($root->id, NodeType::Folder, NodeName::fromString('Documents'), NodePath::forChild($root->path, $root->id));
    $photos = $repo->create($root->id, NodeType::Folder, NodeName::fromString('Photos'), NodePath::forChild($root->path, $root->id));
    $invoices = $repo->create($documents->id, NodeType::Folder, NodeName::fromString('Invoices'), NodePath::forChild($documents->path, $documents->id));
    $march = $repo->create($invoices->id, NodeType::File, NodeName::fromString('march.pdf'), NodePath::forChild($invoices->path, $invoices->id));

    $repo->deleteSubtree($documents);

    expect($repo->find($documents->id))->toBeNull()
        ->and($repo->find($invoices->id))->toBeNull()
        ->and($repo->find($march->id))->toBeNull()
        ->and($repo->find($photos->id))->not->toBeNull();
})->with('repositories');

test('exact-name search finds files sharing a name across different folders, paginated', function (NodeRepository $repo) {
    $root = $repo->findRoot();
    $a = $repo->create($root->id, NodeType::Folder, NodeName::fromString('A'), NodePath::forChild($root->path, $root->id));
    $b = $repo->create($root->id, NodeType::Folder, NodeName::fromString('B'), NodePath::forChild($root->path, $root->id));
    $c = $repo->create($root->id, NodeType::Folder, NodeName::fromString('C'), NodePath::forChild($root->path, $root->id));

    $fileA = $repo->create($a->id, NodeType::File, NodeName::fromString('report.pdf'), NodePath::forChild($a->path, $a->id));
    $fileB = $repo->create($b->id, NodeType::File, NodeName::fromString('REPORT.PDF'), NodePath::forChild($b->path, $b->id));
    $fileC = $repo->create($c->id, NodeType::File, NodeName::fromString('report.pdf'), NodePath::forChild($c->path, $c->id));
    $repo->create($a->id, NodeType::File, NodeName::fromString('other.pdf'), NodePath::forChild($a->path, $a->id));

    $firstPage = $repo->findFilesByName(NodeName::fromString('report.pdf'), null, null, 2);
    expect($firstPage->items)->toHaveCount(2)
        ->and($firstPage->nextCursor)->not->toBeNull();

    $secondPage = $repo->findFilesByName(NodeName::fromString('report.pdf'), null, $firstPage->nextCursor, 2);
    expect($secondPage->items)->toHaveCount(1)
        ->and($secondPage->nextCursor)->toBeNull();

    $foundIds = [...array_map(fn (NodeData $n): int => $n->id, $firstPage->items), ...array_map(fn (NodeData $n): int => $n->id, $secondPage->items)];
    sort($foundIds);
    $expectedIds = [$fileA->id, $fileB->id, $fileC->id];
    sort($expectedIds);
    expect($foundIds)->toBe($expectedIds);
})->with('repositories');

test('exact-name search can be scoped to a subtree', function (NodeRepository $repo) {
    $root = $repo->findRoot();
    $a = $repo->create($root->id, NodeType::Folder, NodeName::fromString('A'), NodePath::forChild($root->path, $root->id));
    $b = $repo->create($root->id, NodeType::Folder, NodeName::fromString('B'), NodePath::forChild($root->path, $root->id));

    $fileA = $repo->create($a->id, NodeType::File, NodeName::fromString('notes.txt'), NodePath::forChild($a->path, $a->id));
    $repo->create($b->id, NodeType::File, NodeName::fromString('notes.txt'), NodePath::forChild($b->path, $b->id));

    $page = $repo->findFilesByName(NodeName::fromString('notes.txt'), $a->id, null, 10);

    expect($page->items)->toHaveCount(1)
        ->and($page->items[0]->id)->toBe($fileA->id);
})->with('repositories');

test('prefix suggestions are case-insensitive and ordered', function (NodeRepository $repo) {
    $root = $repo->findRoot();
    $repo->create($root->id, NodeType::File, NodeName::fromString('Invoice-march.pdf'), NodePath::forChild($root->path, $root->id));
    $repo->create($root->id, NodeType::File, NodeName::fromString('invoice-april.pdf'), NodePath::forChild($root->path, $root->id));
    $repo->create($root->id, NodeType::File, NodeName::fromString('report.pdf'), NodePath::forChild($root->path, $root->id));

    $results = $repo->suggestFilesByPrefix('invoice', null, 10);

    expect(names(...$results))->toBe(['invoice-april.pdf', 'Invoice-march.pdf']);
})->with('repositories');

test('prefix suggestions respect the limit', function (NodeRepository $repo) {
    $root = $repo->findRoot();
    foreach (range(1, 5) as $i) {
        $repo->create($root->id, NodeType::File, NodeName::fromString("file{$i}.txt"), NodePath::forChild($root->path, $root->id));
    }

    $results = $repo->suggestFilesByPrefix('file', null, 3);

    expect($results)->toHaveCount(3);
})->with('repositories');

test('prefix suggestions can be scoped to a subtree', function (NodeRepository $repo) {
    $root = $repo->findRoot();
    $a = $repo->create($root->id, NodeType::Folder, NodeName::fromString('A'), NodePath::forChild($root->path, $root->id));
    $b = $repo->create($root->id, NodeType::Folder, NodeName::fromString('B'), NodePath::forChild($root->path, $root->id));

    $repo->create($a->id, NodeType::File, NodeName::fromString('invoice-a.pdf'), NodePath::forChild($a->path, $a->id));
    $repo->create($b->id, NodeType::File, NodeName::fromString('invoice-b.pdf'), NodePath::forChild($b->path, $b->id));

    $results = $repo->suggestFilesByPrefix('invoice', $a->id, 10);

    expect(names(...$results))->toBe(['invoice-a.pdf']);
})->with('repositories');

test('an escaped literal percent sign matches only a literal percent sign', function (NodeRepository $repo) {
    $root = $repo->findRoot();
    $repo->create($root->id, NodeType::File, NodeName::fromString('100%.txt'), NodePath::forChild($root->path, $root->id));
    $repo->create($root->id, NodeType::File, NodeName::fromString('100x.txt'), NodePath::forChild($root->path, $root->id));

    $results = $repo->suggestFilesByPrefix('100\\%', null, 10);

    expect(names(...$results))->toBe(['100%.txt']);
})->with('repositories');

test('an escaped literal underscore does not act as a single-character wildcard', function (NodeRepository $repo) {
    $root = $repo->findRoot();
    $repo->create($root->id, NodeType::File, NodeName::fromString('a_b.txt'), NodePath::forChild($root->path, $root->id));
    $repo->create($root->id, NodeType::File, NodeName::fromString('axb.txt'), NodePath::forChild($root->path, $root->id));

    $results = $repo->suggestFilesByPrefix('a\\_b', null, 10);

    expect(names(...$results))->toBe(['a_b.txt']);
})->with('repositories');
