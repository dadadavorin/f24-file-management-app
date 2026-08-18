<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('only one root row can exist', function () {
    DB::table('nodes')->insert([
        'parent_id' => null,
        'type' => 'folder',
        'name' => 'Second Root',
        'path' => '/',
        'depth' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

test('two nodes cannot share a name, case-insensitively, within the same parent', function () {
    $root = DB::table('nodes')->whereNull('parent_id')->first();

    DB::table('nodes')->insert([
        'parent_id' => $root->id,
        'type' => 'folder',
        'name' => 'Documents',
        'path' => '/'.$root->id.'/',
        'depth' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('nodes')->insert([
        'parent_id' => $root->id,
        'type' => 'folder',
        'name' => 'documents',
        'path' => '/'.$root->id.'/',
        'depth' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);

test('deleting a folder cascades to its children at the database level', function () {
    // The FK cascade is a correctness net independent of the repository's own
    // two-statement subtree delete (ADR-0001).
    $root = DB::table('nodes')->whereNull('parent_id')->first();

    $folderId = DB::table('nodes')->insertGetId([
        'parent_id' => $root->id,
        'type' => 'folder',
        'name' => 'Documents',
        'path' => '/'.$root->id.'/',
        'depth' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $childId = DB::table('nodes')->insertGetId([
        'parent_id' => $folderId,
        'type' => 'file',
        'name' => 'notes.txt',
        'path' => '/'.$root->id.'/'.$folderId.'/',
        'depth' => 2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('nodes')->where('id', $folderId)->delete();

    expect(DB::table('nodes')->where('id', $childId)->exists())->toBeFalse();
});
