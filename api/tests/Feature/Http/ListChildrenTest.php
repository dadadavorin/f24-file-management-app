<?php

declare(strict_types=1);

use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\ValueObject\NodeName;
use App\Domain\FileSystem\ValueObject\NodePath;

test('children are listed folders-first then alphabetically, with child counts', function () {
    $root = repository()->findRoot();
    repository()->create($root->id, NodeType::File, NodeName::fromString('b.txt'), NodePath::forChild($root->path, $root->id));
    $documents = repository()->create($root->id, NodeType::Folder, NodeName::fromString('Documents'), NodePath::forChild($root->path, $root->id));
    repository()->create($documents->id, NodeType::File, NodeName::fromString('one.txt'), NodePath::forChild($documents->path, $documents->id));

    $response = $this->getJson("/api/v1/nodes/{$root->id}/children");

    $response->assertOk()
        ->assertJsonPath('data.0.name', 'Documents')
        ->assertJsonPath('data.0.type', 'folder')
        ->assertJsonPath('data.0.child_count', 1)
        ->assertJsonPath('data.1.name', 'b.txt')
        ->assertJsonPath('data.1.type', 'file')
        ->assertJsonMissingPath('data.1.child_count')
        ->assertJsonPath('meta.next_cursor', null);
});

test('pagination is exact across a page boundary via the cursor', function () {
    $root = repository()->findRoot();

    foreach (['Alpha', 'Bravo', 'Charlie'] as $name) {
        repository()->create($root->id, NodeType::Folder, NodeName::fromString($name), NodePath::forChild($root->path, $root->id));
    }

    $firstPage = $this->getJson("/api/v1/nodes/{$root->id}/children?limit=2");
    $firstPage->assertOk()
        ->assertJsonPath('data.0.name', 'Alpha')
        ->assertJsonPath('data.1.name', 'Bravo');

    $cursor = $firstPage->json('meta.next_cursor');
    expect($cursor)->not->toBeNull();

    $secondPage = $this->getJson('/api/v1/nodes/'.$root->id.'/children?limit=2&cursor='.urlencode((string) $cursor));
    $secondPage->assertOk()
        ->assertJsonPath('data.0.name', 'Charlie')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.next_cursor', null);
});

test('listing children of a missing folder returns a 404 problem', function () {
    $response = $this->getJson('/api/v1/nodes/999999/children');

    $response->assertStatus(404)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'NODE_NOT_FOUND');
});

test('listing children of a file returns a 422 problem', function () {
    $root = repository()->findRoot();
    $file = repository()->create($root->id, NodeType::File, NodeName::fromString('a.txt'), NodePath::forChild($root->path, $root->id));

    $response = $this->getJson("/api/v1/nodes/{$file->id}/children");

    $response->assertStatus(422)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'PARENT_IS_NOT_A_FOLDER');
});
