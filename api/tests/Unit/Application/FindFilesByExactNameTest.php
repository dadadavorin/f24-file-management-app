<?php

declare(strict_types=1);

use App\Application\FileSystem\FindFilesByExactName;
use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\Exception\InvalidNodeName;
use App\Domain\FileSystem\ValueObject\NodeName;
use App\Domain\FileSystem\ValueObject\NodePath;
use Tests\Fakes\InMemoryNodeRepository;

test('files sharing a name are found across different folders', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();
    $a = $repository->create($root->id, NodeType::Folder, NodeName::fromString('A'), NodePath::forChild($root->path, $root->id));
    $b = $repository->create($root->id, NodeType::Folder, NodeName::fromString('B'), NodePath::forChild($root->path, $root->id));

    $repository->create($a->id, NodeType::File, NodeName::fromString('report.pdf'), NodePath::forChild($a->path, $a->id));
    $repository->create($b->id, NodeType::File, NodeName::fromString('report.pdf'), NodePath::forChild($b->path, $b->id));

    $result = (new FindFilesByExactName($repository))->execute('report.pdf', null, null, 10);

    expect($result['page']->items)->toHaveCount(2);
});

test('the containing folder of every result is resolved in the folders map', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();
    $documents = $repository->create($root->id, NodeType::Folder, NodeName::fromString('Documents'), NodePath::forChild($root->path, $root->id));
    $invoices = $repository->create($documents->id, NodeType::Folder, NodeName::fromString('Invoices'), NodePath::forChild($documents->path, $documents->id));
    $repository->create($invoices->id, NodeType::File, NodeName::fromString('march.pdf'), NodePath::forChild($invoices->path, $invoices->id));

    $result = (new FindFilesByExactName($repository))->execute('march.pdf', null, null, 10);

    expect($result['folders'])->toHaveKey($root->id)
        ->and($result['folders'])->toHaveKey($documents->id)
        ->and($result['folders'])->toHaveKey($invoices->id)
        ->and($result['folders'][$invoices->id]->name)->toBe('Invoices');
});

test('search can be scoped to a subtree', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();
    $a = $repository->create($root->id, NodeType::Folder, NodeName::fromString('A'), NodePath::forChild($root->path, $root->id));
    $b = $repository->create($root->id, NodeType::Folder, NodeName::fromString('B'), NodePath::forChild($root->path, $root->id));

    $fileA = $repository->create($a->id, NodeType::File, NodeName::fromString('notes.txt'), NodePath::forChild($a->path, $a->id));
    $repository->create($b->id, NodeType::File, NodeName::fromString('notes.txt'), NodePath::forChild($b->path, $b->id));

    $result = (new FindFilesByExactName($repository))->execute('notes.txt', $a->id, null, 10);

    expect($result['page']->items)->toHaveCount(1)
        ->and($result['page']->items[0]->id)->toBe($fileA->id);
});

test('results are paginated across a page boundary', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();

    foreach (range(1, 3) as $i) {
        $folder = $repository->create($root->id, NodeType::Folder, NodeName::fromString("Folder{$i}"), NodePath::forChild($root->path, $root->id));
        $repository->create($folder->id, NodeType::File, NodeName::fromString('report.pdf'), NodePath::forChild($folder->path, $folder->id));
    }

    $action = new FindFilesByExactName($repository);

    $firstPage = $action->execute('report.pdf', null, null, 2);
    expect($firstPage['page']->items)->toHaveCount(2)
        ->and($firstPage['page']->nextCursor)->not->toBeNull();

    $secondPage = $action->execute('report.pdf', null, $firstPage['page']->nextCursor, 2);
    expect($secondPage['page']->items)->toHaveCount(1)
        ->and($secondPage['page']->nextCursor)->toBeNull();
});

test('a name that could never be valid throws InvalidNodeName', function () {
    (new FindFilesByExactName(new InMemoryNodeRepository))->execute('a/b', null, null, 10);
})->throws(InvalidNodeName::class);

test('no results means an empty folders map', function () {
    $result = (new FindFilesByExactName(new InMemoryNodeRepository))->execute('missing.pdf', null, null, 10);

    expect($result['page']->items)->toBe([])
        ->and($result['folders'])->toBe([]);
});
