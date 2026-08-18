<?php

declare(strict_types=1);

use App\Application\FileSystem\DeleteNode;
use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\Exception\NodeNotFound;
use App\Domain\FileSystem\Exception\RootIsImmutable;
use App\Domain\FileSystem\ValueObject\NodeName;
use App\Domain\FileSystem\ValueObject\NodePath;
use Tests\Fakes\InMemoryNodeRepository;

test('deleting a folder removes its whole subtree', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();
    $documents = $repository->create($root->id, NodeType::Folder, NodeName::fromString('Documents'), NodePath::forChild($root->path, $root->id));
    $invoices = $repository->create($documents->id, NodeType::Folder, NodeName::fromString('Invoices'), NodePath::forChild($documents->path, $documents->id));
    $march = $repository->create($invoices->id, NodeType::File, NodeName::fromString('march.pdf'), NodePath::forChild($invoices->path, $invoices->id));

    (new DeleteNode($repository))->execute($documents->id);

    expect($repository->find($documents->id))->toBeNull()
        ->and($repository->find($invoices->id))->toBeNull()
        ->and($repository->find($march->id))->toBeNull();
});

test('deleting leaves siblings intact', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();
    $documents = $repository->create($root->id, NodeType::Folder, NodeName::fromString('Documents'), NodePath::forChild($root->path, $root->id));
    $photos = $repository->create($root->id, NodeType::Folder, NodeName::fromString('Photos'), NodePath::forChild($root->path, $root->id));

    (new DeleteNode($repository))->execute($documents->id);

    expect($repository->find($photos->id))->not->toBeNull();
});

test('deleting a missing node throws NodeNotFound', function () {
    (new DeleteNode(new InMemoryNodeRepository))->execute(999999);
})->throws(NodeNotFound::class);

test('deleting the root throws RootIsImmutable', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();

    (new DeleteNode($repository))->execute($root->id);
})->throws(RootIsImmutable::class);
