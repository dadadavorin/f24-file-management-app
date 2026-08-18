<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $type
 * @property int $sort_rank
 * @property string $name
 * @property string $path
 * @property int $depth
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class Node extends Model
{
    protected $fillable = ['parent_id', 'type', 'name', 'path', 'depth'];

    protected $casts = [
        'parent_id' => 'integer',
        'sort_rank' => 'integer',
        'depth' => 'integer',
    ];
}
