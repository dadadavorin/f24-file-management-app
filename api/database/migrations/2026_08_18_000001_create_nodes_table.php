<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Five indexes carry the tree's performance:
 *
 *   1. nodes_single_root           at most one row with parent_id IS NULL, ever.
 *   2. nodes_unique_name_per_parent
 *                                  case-insensitive name uniqueness within a
 *                                  folder, enforced under concurrency; also
 *                                  what makes (sort_rank, lower(name)) a
 *                                  unique keyset cursor within a parent.
 *   3. nodes_children_listing      folder listing in display order. All
 *                                  columns ascending — sort_rank turns
 *                                  "folders before files" into a value
 *                                  comparison instead of a sort direction,
 *                                  which keeps the keyset cursor a plain
 *                                  row comparison.
 *   4. nodes_file_name             prefix and exact search. COLLATE "C" on
 *                                  the indexed expression gives both the
 *                                  LIKE range scan and a usable ORDER BY
 *                                  from one index. Drop lower() or
 *                                  COLLATE "C" here, or in a query, and the
 *                                  planner silently falls back to a Sort.
 *   5. nodes_path                  subtree scans. Deliberately NOT partial:
 *                                  a folders- or files-only predicate here
 *                                  would force the subtree DELETE to
 *                                  sequentially scan the whole table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('nodes')->cascadeOnDelete();
            $table->enum('type', ['folder', 'file']);
            $table->smallInteger('sort_rank')->storedAs("case when type = 'folder' then 0 else 1 end");
            $table->string('name', 255);
            $table->string('path', 1024);
            $table->smallInteger('depth');
            $table->timestampsTz();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX nodes_single_root
                ON nodes ((parent_id IS NULL)) WHERE parent_id IS NULL
            SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX nodes_unique_name_per_parent
                ON nodes (parent_id, lower(name))
            SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX nodes_children_listing
                ON nodes (parent_id, sort_rank, (lower(name) COLLATE "C"))
            SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX nodes_file_name
                ON nodes ((lower(name) COLLATE "C")) WHERE type = 'file'
            SQL);

        DB::statement(<<<'SQL'
            CREATE INDEX nodes_path ON nodes (path COLLATE "C")
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
