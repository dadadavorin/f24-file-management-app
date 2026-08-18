<?php

test('the health endpoint returns ok', function () {
    $this->get('/api/v1/health')
        ->assertStatus(200)
        ->assertJson(['status' => 'ok']);
});
