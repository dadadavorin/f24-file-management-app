<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\FileSystem\Repository\NodeRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentNodeRepository;
use Illuminate\Support\ServiceProvider;

final class FileSystemServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NodeRepository::class, EloquentNodeRepository::class);
    }
}
