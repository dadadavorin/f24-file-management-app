<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\FileSystem\Repository\NodeRepository;
use App\Http\OpenApi\NameLengthConstraintExtension;
use App\Infrastructure\Persistence\Eloquent\EloquentNodeRepository;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;

final class FileSystemServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NodeRepository::class, EloquentNodeRepository::class);

        // Scramble reads its extension list while booting, which runs before
        // this provider's own boot() — registering here, in the register()
        // phase that precedes every provider's boot(), is the only ordering
        // that's guaranteed to land before that read.
        Scramble::registerExtension(NameLengthConstraintExtension::class);
    }
}
