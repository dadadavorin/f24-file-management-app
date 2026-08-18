<?php

declare(strict_types=1);

use App\Application\FileSystem\CreateNode;
use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\Exception\DuplicateNodeName;
use App\Domain\FileSystem\Exception\InvalidNodeName;
use App\Domain\FileSystem\Exception\MaxDepthExceeded;
use App\Domain\FileSystem\Exception\NodeNotFound;
use App\Domain\FileSystem\Exception\ParentIsNotAFolder;
use App\Domain\FileSystem\ValueObject\NodeName;
use App\Domain\FileSystem\ValueObject\NodePath;
use Tests\Fakes\InMemoryNodeRepository;

test('a folder is created under the root', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();
    $action = new CreateNode($repository);

    $created = $action->execute($root->id, NodeType::Folder, 'Documents');

    expect($created->type)->toBe(NodeType::Folder)
        ->and($created->name)->toBe('Documents')
        ->and($created->parentId)->toBe($root->id)
        ->and($created->path->value)->toBe('/'.$root->id.'/');
});

test('a file is created with the same action, type is just a parameter', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();
    $action = new CreateNode($repository);

    $created = $action->execute($root->id, NodeType::File, 'notes.txt');

    expect($created->type)->toBe(NodeType::File)
        ->and($created->name)->toBe('notes.txt');
});

test('creating under a missing parent throws NodeNotFound', function () {
    $repository = new InMemoryNodeRepository;
    $action = new CreateNode($repository);

    $action->execute(999999, NodeType::Folder, 'Documents');
})->throws(NodeNotFound::class);

test('creating under a file throws ParentIsNotAFolder', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();
    $file = $repository->create(
        $root->id,
        NodeType::File,
        NodeName::fromString('a.txt'),
        NodePath::forChild($root->path, $root->id),
    );
    $action = new CreateNode($repository);

    $action->execute($file->id, NodeType::Folder, 'Sub');
})->throws(ParentIsNotAFolder::class);

test('an invalid name throws InvalidNodeName before the repository is touched', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();
    $action = new CreateNode($repository);

    $action->execute($root->id, NodeType::Folder, '');
})->throws(InvalidNodeName::class);

test('nesting past the maximum depth throws MaxDepthExceeded', function () {
    $repository = new InMemoryNodeRepository;
    $action = new CreateNode($repository);

    $parentId = $repository->findRoot()->id;

    for ($i = 1; $i <= NodePath::MAX_DEPTH; $i++) {
        $parentId = $action->execute($parentId, NodeType::Folder, "level{$i}")->id;
    }

    $action->execute($parentId, NodeType::Folder, 'one-too-deep');
})->throws(MaxDepthExceeded::class);

test('there is no pre-insert existence check, the repository arbitrates duplicates', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();
    $action = new CreateNode($repository);

    $action->execute($root->id, NodeType::Folder, 'Documents');

    $action->execute($root->id, NodeType::Folder, 'documents');
})->throws(DuplicateNodeName::class);
