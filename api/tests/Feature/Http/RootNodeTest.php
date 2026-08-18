<?php

declare(strict_types=1);

test('the root node is returned with no parent', function () {
    $root = repository()->findRoot();

    $response = $this->getJson('/api/v1/nodes/root');

    $response->assertOk()
        ->assertJsonPath('data.id', $root->id)
        ->assertJsonPath('data.parent_id', null)
        ->assertJsonPath('data.type', 'folder');
});
