<?php

declare(strict_types=1);

use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\ValueObject\NodeName;
use App\Domain\FileSystem\ValueObject\NodePath;

test('prefix suggestions are case-insensitive and ordered', function () {
    $root = repository()->findRoot();
    repository()->create($root->id, NodeType::File, NodeName::fromString('Invoice-march.pdf'), NodePath::forChild($root->path, $root->id));
    repository()->create($root->id, NodeType::File, NodeName::fromString('invoice-april.pdf'), NodePath::forChild($root->path, $root->id));
    repository()->create($root->id, NodeType::File, NodeName::fromString('report.pdf'), NodePath::forChild($root->path, $root->id));

    $response = $this->getJson('/api/v1/search/suggestions?q=invoice');

    $response->assertOk()
        ->assertJsonPath('data.0.name', 'invoice-april.pdf')
        ->assertJsonPath('data.1.name', 'Invoice-march.pdf')
        ->assertJsonCount(2, 'data');
});

test('a blank query returns an empty list without touching the database', function () {
    $response = $this->getJson('/api/v1/search/suggestions?q=');

    $response->assertOk()->assertExactJson(['data' => []]);
});

test('a lone percent sign matches nothing and does not error', function () {
    $root = repository()->findRoot();
    repository()->create($root->id, NodeType::File, NodeName::fromString('100%.txt'), NodePath::forChild($root->path, $root->id));

    $response = $this->getJson('/api/v1/search/suggestions?'.http_build_query(['q' => '%']));

    $response->assertOk()->assertExactJson(['data' => []]);
});

test('suggestions can be scoped to a subtree', function () {
    $root = repository()->findRoot();
    $a = repository()->create($root->id, NodeType::Folder, NodeName::fromString('A'), NodePath::forChild($root->path, $root->id));
    $b = repository()->create($root->id, NodeType::Folder, NodeName::fromString('B'), NodePath::forChild($root->path, $root->id));
    repository()->create($a->id, NodeType::File, NodeName::fromString('invoice-a.pdf'), NodePath::forChild($a->path, $a->id));
    repository()->create($b->id, NodeType::File, NodeName::fromString('invoice-b.pdf'), NodePath::forChild($b->path, $b->id));

    $response = $this->getJson("/api/v1/search/suggestions?q=invoice&scope=subtree&parent_id={$a->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'invoice-a.pdf');
});
