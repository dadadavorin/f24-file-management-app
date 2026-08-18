<?php

declare(strict_types=1);

use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\ValueObject\NodeName;
use App\Domain\FileSystem\ValueObject\NodePath;

test('a folder is created under the root', function () {
    $root = repository()->findRoot();

    $response = $this->postJson('/api/v1/nodes', [
        'parent_id' => $root->id,
        'type' => 'folder',
        'name' => 'Documents',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Documents')
        ->assertJsonPath('data.type', 'folder')
        ->assertJsonPath('data.parent_id', $root->id)
        ->assertJsonPath('data.child_count', null);
});

test('a file is created with the same endpoint, type is just a parameter', function () {
    $root = repository()->findRoot();

    $response = $this->postJson('/api/v1/nodes', [
        'parent_id' => $root->id,
        'type' => 'file',
        'name' => 'notes.txt',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'notes.txt')
        ->assertJsonPath('data.type', 'file')
        ->assertJsonMissingPath('data.child_count');
});

test('a blank name returns a 422 with errors keyed by name', function () {
    $root = repository()->findRoot();

    $response = $this->postJson('/api/v1/nodes', [
        'parent_id' => $root->id,
        'type' => 'folder',
        'name' => '',
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['message', 'errors' => ['name']]);
});

test('creating under a missing parent returns a 404 problem', function () {
    $response = $this->postJson('/api/v1/nodes', [
        'parent_id' => 999999,
        'type' => 'folder',
        'name' => 'Documents',
    ]);

    $response->assertStatus(404)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'NODE_NOT_FOUND');
});

test('creating under a file returns a 422 problem', function () {
    $root = repository()->findRoot();
    $file = repository()->create($root->id, NodeType::File, NodeName::fromString('a.txt'), NodePath::forChild($root->path, $root->id));

    $response = $this->postJson('/api/v1/nodes', [
        'parent_id' => $file->id,
        'type' => 'folder',
        'name' => 'Sub',
    ]);

    $response->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'PARENT_IS_NOT_A_FOLDER');
});

test('nesting past the maximum depth returns a 422 problem', function () {
    $parentId = repository()->findRoot()->id;

    for ($i = 1; $i <= NodePath::MAX_DEPTH; $i++) {
        $node = repository()->create($parentId, NodeType::Folder, NodeName::fromString("level{$i}"), NodePath::forChild(repository()->find($parentId)->path, $parentId));
        $parentId = $node->id;
    }

    $response = $this->postJson('/api/v1/nodes', [
        'parent_id' => $parentId,
        'type' => 'folder',
        'name' => 'one-too-deep',
    ]);

    $response->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'MAX_DEPTH_EXCEEDED');
});

test('a duplicate name in the same parent returns a 409 problem', function () {
    $root = repository()->findRoot();
    repository()->create($root->id, NodeType::Folder, NodeName::fromString('Documents'), NodePath::forChild($root->path, $root->id));

    $response = $this->postJson('/api/v1/nodes', [
        'parent_id' => $root->id,
        'type' => 'folder',
        'name' => 'documents',
    ]);

    $response->assertStatus(409)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'DUPLICATE_NODE_NAME');
});
