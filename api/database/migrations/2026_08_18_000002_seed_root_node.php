<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('nodes')->insert([
            'parent_id' => null,
            'type' => 'folder',
            'name' => 'Root',
            'path' => '/',
            'depth' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('nodes')->whereNull('parent_id')->delete();
    }
};
