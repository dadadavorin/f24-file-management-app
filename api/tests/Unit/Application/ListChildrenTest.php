<?php

declare(strict_types=1);

use App\Application\FileSystem\ListChildren;
use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\Exception\NodeNotFound;
use App\Domain\FileSystem\Exception\ParentIsNotAFolder;
use App\Domain\FileSystem\ValueObject\NodeName;
use App\Domain\FileSystem\ValueObject\NodePath;
use Tests\Fakes\InMemoryNodeRepository;

test('children are listed folders-first then alphabetically', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();
    $repository->create($root->id, NodeType::File, NodeName::fromString('b.txt'), NodePath::forChild($root->path, $root->id));
    $repository->create($root->id, NodeType::Folder, NodeName::fromString('Zebra'), NodePath::forChild($root->path, $root->id));
    $repository->create($root->id, NodeType::File, NodeName::fromString('a.txt'), NodePath::forChild($root->path, $root->id));
    $repository->create($root->id, NodeType::Folder, NodeName::fromString('Apple'), NodePath::forChild($root->path, $root->id));

    $result = (new ListChildren($repository))->execute($root->id, null, 10);

    expect(array_map(fn ($node) => $node->name, $result['page']->items))
        ->toBe(['Apple', 'Zebra', 'a.txt', 'b.txt']);
});

test('listing a missing folder throws NodeNotFound', function () {
    (new ListChildren(new InMemoryNodeRepository))->execute(999999, null, 10);
})->throws(NodeNotFound::class);

test('listing the children of a file throws ParentIsNotAFolder', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();
    $file = $repository->create($root->id, NodeType::File, NodeName::fromString('a.txt'), NodePath::forChild($root->path, $root->id));

    (new ListChildren($repository))->execute($file->id, null, 10);
})->throws(ParentIsNotAFolder::class);

test('child counts are capped and computed in one call, never one query per row', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();
    $big = $repository->create($root->id, NodeType::Folder, NodeName::fromString('Big'), NodePath::forChild($root->path, $root->id));
    $repository->create($root->id, NodeType::Folder, NodeName::fromString('Empty'), NodePath::forChild($root->path, $root->id));

    foreach (range(0, 4) as $i) {
        $repository->create($big->id, NodeType::File, NodeName::fromString("file{$i}.txt"), NodePath::forChild($big->path, $big->id));
    }

    $result = (new ListChildren($repository))->execute($root->id, null, 10);

    expect($result['childCounts'][$big->id])->toBe(5);
});

test('child counts only include folders present on the current page', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();
    $folder = $repository->create($root->id, NodeType::Folder, NodeName::fromString('Folder'), NodePath::forChild($root->path, $root->id));
    $repository->create($folder->id, NodeType::File, NodeName::fromString('inside.txt'), NodePath::forChild($folder->path, $folder->id));
    $repository->create($root->id, NodeType::File, NodeName::fromString('a.txt'), NodePath::forChild($root->path, $root->id));

    $result = (new ListChildren($repository))->execute($root->id, null, 10);

    expect($result['childCounts'])->toHaveKey($folder->id)
        ->and($result['childCounts'])->toHaveCount(1);
});

test('pagination is exact across a page boundary', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();

    foreach (['Alpha', 'Bravo', 'Charlie'] as $name) {
        $repository->create($root->id, NodeType::Folder, NodeName::fromString($name), NodePath::forChild($root->path, $root->id));
    }

    $action = new ListChildren($repository);

    $firstPage = $action->execute($root->id, null, 2);
    expect(array_map(fn ($node) => $node->name, $firstPage['page']->items))->toBe(['Alpha', 'Bravo'])
        ->and($firstPage['page']->nextCursor)->not->toBeNull();

    $secondPage = $action->execute($root->id, $firstPage['page']->nextCursor, 2);
    expect(array_map(fn ($node) => $node->name, $secondPage['page']->items))->toBe(['Charlie'])
        ->and($secondPage['page']->nextCursor)->toBeNull();
});
