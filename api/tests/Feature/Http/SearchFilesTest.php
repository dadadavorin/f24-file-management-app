<?php

declare(strict_types=1);

use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\ValueObject\NodeName;
use App\Domain\FileSystem\ValueObject\NodePath;

test('exact-name search finds files sharing a name and labels their folder', function () {
    $root = repository()->findRoot();
    $a = repository()->create($root->id, NodeType::Folder, NodeName::fromString('A'), NodePath::forChild($root->path, $root->id));
    $b = repository()->create($root->id, NodeType::Folder, NodeName::fromString('B'), NodePath::forChild($root->path, $root->id));
    repository()->create($a->id, NodeType::File, NodeName::fromString('report.pdf'), NodePath::forChild($a->path, $a->id));
    repository()->create($b->id, NodeType::File, NodeName::fromString('report.pdf'), NodePath::forChild($b->path, $b->id));

    $response = $this->getJson('/api/v1/search/files?name=report.pdf&scope=all');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.folder.name', 'A')
        ->assertJsonPath('data.1.folder.name', 'B');
});

test('exact-name search can be scoped to a subtree', function () {
    $root = repository()->findRoot();
    $a = repository()->create($root->id, NodeType::Folder, NodeName::fromString('A'), NodePath::forChild($root->path, $root->id));
    $b = repository()->create($root->id, NodeType::Folder, NodeName::fromString('B'), NodePath::forChild($root->path, $root->id));
    $fileA = repository()->create($a->id, NodeType::File, NodeName::fromString('notes.txt'), NodePath::forChild($a->path, $a->id));
    repository()->create($b->id, NodeType::File, NodeName::fromString('notes.txt'), NodePath::forChild($b->path, $b->id));

    $response = $this->getJson("/api/v1/search/files?name=notes.txt&scope=subtree&parent_id={$a->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $fileA->id);
});

test('a scope of subtree without a parent_id returns a 422 validation error', function () {
    $response = $this->getJson('/api/v1/search/files?name=notes.txt&scope=subtree');

    $response->assertStatus(422)
        ->assertJsonStructure(['message', 'errors' => ['parent_id']]);
});

test('a blank search name returns a 422 with errors keyed by name', function () {
    $response = $this->getJson('/api/v1/search/files?name=&scope=all');

    $response->assertStatus(422)
        ->assertJsonStructure(['message', 'errors' => ['name']]);
});
