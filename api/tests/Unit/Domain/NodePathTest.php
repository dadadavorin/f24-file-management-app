<?php

declare(strict_types=1);

use App\Domain\FileSystem\Exception\MaxDepthExceeded;
use App\Domain\FileSystem\ValueObject\NodePath;

test('the root path is the empty ancestor chain at depth zero', function () {
    $root = NodePath::forRoot();

    expect($root->value)->toBe('/')
        ->and($root->depth)->toBe(0);
});

test('a child path appends the parent id to the parent path', function () {
    $root = NodePath::forRoot();
    $documents = NodePath::forChild($root, 1);

    expect($documents->value)->toBe('/1/')
        ->and($documents->depth)->toBe(1);
});

test('depth increases by one at every level of nesting', function () {
    $root = NodePath::forRoot();
    $documents = NodePath::forChild($root, 1);
    $invoices = NodePath::forChild($documents, 7);
    $march = NodePath::forChild($invoices, 22);

    expect($march->value)->toBe('/1/7/22/')
        ->and($march->depth)->toBe(3);
});

test('subtree pattern appends the node own id to its path', function () {
    $documents = NodePath::forChild(NodePath::forRoot(), 1);

    expect($documents->subtreePattern(7))->toBe('/1/7/%');
});

test('ancestor ids are parsed from a nested path', function () {
    $path = NodePath::forChild(
        NodePath::forChild(NodePath::forChild(NodePath::forRoot(), 1), 7),
        22,
    );

    expect($path->ancestorIds())->toBe([1, 7, 22]);
});

test('the root path has no ancestors', function () {
    expect(NodePath::forRoot()->ancestorIds())->toBe([]);
});

test('a stored path reconstructs without re-running the depth check', function () {
    $path = NodePath::fromStored('/1/7/22/', 3);

    expect($path->value)->toBe('/1/7/22/')
        ->and($path->depth)->toBe(3);
});

test('building a child past the maximum depth throws', function () {
    $path = NodePath::forRoot();

    for ($i = 1; $i <= NodePath::MAX_DEPTH; $i++) {
        $path = NodePath::forChild($path, $i);
    }

    expect($path->depth)->toBe(NodePath::MAX_DEPTH);

    NodePath::forChild($path, NodePath::MAX_DEPTH + 1);
})->throws(MaxDepthExceeded::class);
