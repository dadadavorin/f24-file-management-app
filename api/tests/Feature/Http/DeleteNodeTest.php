<?php

declare(strict_types=1);

use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\ValueObject\NodeName;
use App\Domain\FileSystem\ValueObject\NodePath;

test('deleting a folder removes its whole subtree', function () {
    $root = repository()->findRoot();
    $documents = repository()->create($root->id, NodeType::Folder, NodeName::fromString('Documents'), NodePath::forChild($root->path, $root->id));
    $note = repository()->create($documents->id, NodeType::File, NodeName::fromString('note.txt'), NodePath::forChild($documents->path, $documents->id));

    $response = $this->deleteJson("/api/v1/nodes/{$documents->id}");

    $response->assertNoContent();
    expect(repository()->find($documents->id))->toBeNull()
        ->and(repository()->find($note->id))->toBeNull();
});

test('deleting a missing node returns a 404 problem', function () {
    $response = $this->deleteJson('/api/v1/nodes/999999');

    $response->assertStatus(404)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'NODE_NOT_FOUND');
});

test('deleting the root returns a 422 problem', function () {
    $root = repository()->findRoot();

    $response = $this->deleteJson("/api/v1/nodes/{$root->id}");

    $response->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'ROOT_IS_IMMUTABLE');
});
