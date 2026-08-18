<?php

declare(strict_types=1);

use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\ValueObject\NodeName;
use App\Domain\FileSystem\ValueObject\NodePath;

test('a node is returned with its ancestor breadcrumb chain', function () {
    $root = repository()->findRoot();
    $documents = repository()->create($root->id, NodeType::Folder, NodeName::fromString('Documents'), NodePath::forChild($root->path, $root->id));
    $invoices = repository()->create($documents->id, NodeType::Folder, NodeName::fromString('Invoices'), NodePath::forChild($documents->path, $documents->id));

    $response = $this->getJson("/api/v1/nodes/{$invoices->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $invoices->id)
        ->assertJsonPath('data.name', 'Invoices')
        ->assertJsonPath('breadcrumbs.0.id', $root->id)
        ->assertJsonPath('breadcrumbs.1.id', $documents->id)
        ->assertJsonCount(2, 'breadcrumbs');
});

test('the root has an empty breadcrumb chain', function () {
    $root = repository()->findRoot();

    $response = $this->getJson("/api/v1/nodes/{$root->id}");

    $response->assertOk()->assertJsonCount(0, 'breadcrumbs');
});

test('a missing node returns a 404 problem', function () {
    $response = $this->getJson('/api/v1/nodes/999999');

    $response->assertStatus(404)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'NODE_NOT_FOUND');
});
