<?php

declare(strict_types=1);

use App\Domain\FileSystem\Exception\FileSystemException;
use App\Domain\FileSystem\Exception\InvalidNodeName;
use App\Http\Rendering\DomainExceptionRenderer;

test('every domain exception subclass is mapped by the renderer', function () {
    $files = glob(app_path('Domain/FileSystem/Exception/*.php'));
    expect($files)->not->toBeFalse();

    $classes = array_map(
        static fn (string $file): string => 'App\\Domain\\FileSystem\\Exception\\'.basename($file, '.php'),
        $files,
    );

    $subclasses = array_filter(
        $classes,
        static fn (string $class): bool => is_subclass_of($class, FileSystemException::class)
            && ! (new ReflectionClass($class))->isAbstract(),
    );

    expect($subclasses)->not->toBeEmpty();

    foreach ($subclasses as $class) {
        $isMapped = $class === InvalidNodeName::class || array_key_exists($class, DomainExceptionRenderer::PROBLEM_MAP);

        expect($isMapped)->toBeTrue("{$class} is not mapped by DomainExceptionRenderer.");
    }
});
