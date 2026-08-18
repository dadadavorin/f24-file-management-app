<?php

declare(strict_types=1);

use App\Application\FileSystem\SuggestFilesByPrefix;
use App\Domain\FileSystem\Dto\Cursor;
use App\Domain\FileSystem\Dto\NodeData;
use App\Domain\FileSystem\Dto\NodePage;
use App\Domain\FileSystem\Enum\NodeType;
use App\Domain\FileSystem\Repository\NodeRepository;
use App\Domain\FileSystem\ValueObject\NodeName;
use App\Domain\FileSystem\ValueObject\NodePath;
use Tests\Fakes\InMemoryNodeRepository;

test('a blank query returns no results without touching the repository', function () {
    $repository = new class implements NodeRepository
    {
        public function find(int $id): ?NodeData
        {
            throw new LogicException('should not be called');
        }

        public function findRoot(): NodeData
        {
            throw new LogicException('should not be called');
        }

        public function findByIds(array $ids): array
        {
            throw new LogicException('should not be called');
        }

        public function children(int $parentId, ?Cursor $cursor, int $limit): NodePage
        {
            throw new LogicException('should not be called');
        }

        public function childCounts(array $folderIds, int $cap): array
        {
            throw new LogicException('should not be called');
        }

        public function create(int $parentId, NodeType $type, NodeName $name, NodePath $path): NodeData
        {
            throw new LogicException('should not be called');
        }

        public function deleteSubtree(NodeData $node): void
        {
            throw new LogicException('should not be called');
        }

        public function findFilesByName(NodeName $name, ?int $subtreeRootId, ?Cursor $cursor, int $limit): NodePage
        {
            throw new LogicException('should not be called');
        }

        public function suggestFilesByPrefix(string $escapedPrefix, ?int $subtreeRootId, int $limit): array
        {
            throw new LogicException('the blank-query short circuit must never reach the repository');
        }
    };

    $results = (new SuggestFilesByPrefix($repository))->execute('   ', null);

    expect($results)->toBe([]);
});

test('suggestions are case-insensitive and ordered', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();
    $repository->create($root->id, NodeType::File, NodeName::fromString('Invoice-march.pdf'), NodePath::forChild($root->path, $root->id));
    $repository->create($root->id, NodeType::File, NodeName::fromString('invoice-april.pdf'), NodePath::forChild($root->path, $root->id));
    $repository->create($root->id, NodeType::File, NodeName::fromString('report.pdf'), NodePath::forChild($root->path, $root->id));

    $results = (new SuggestFilesByPrefix($repository))->execute('invoice', null);

    expect(array_map(fn ($node) => $node->name, $results))->toBe(['invoice-april.pdf', 'Invoice-march.pdf']);
});

test('suggestions are capped at ten', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();

    foreach (range(1, 15) as $i) {
        $repository->create($root->id, NodeType::File, NodeName::fromString("file{$i}.txt"), NodePath::forChild($root->path, $root->id));
    }

    $results = (new SuggestFilesByPrefix($repository))->execute('file', null);

    expect($results)->toHaveCount(10);
});

test('suggestions can be scoped to a subtree', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();
    $a = $repository->create($root->id, NodeType::Folder, NodeName::fromString('A'), NodePath::forChild($root->path, $root->id));
    $b = $repository->create($root->id, NodeType::Folder, NodeName::fromString('B'), NodePath::forChild($root->path, $root->id));

    $repository->create($a->id, NodeType::File, NodeName::fromString('invoice-a.pdf'), NodePath::forChild($a->path, $a->id));
    $repository->create($b->id, NodeType::File, NodeName::fromString('invoice-b.pdf'), NodePath::forChild($b->path, $b->id));

    $results = (new SuggestFilesByPrefix($repository))->execute('invoice', $a->id);

    expect(array_map(fn ($node) => $node->name, $results))->toBe(['invoice-a.pdf']);
});

test('a literal percent sign is escaped and matches only a literal percent sign', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();
    $repository->create($root->id, NodeType::File, NodeName::fromString('100%.txt'), NodePath::forChild($root->path, $root->id));
    $repository->create($root->id, NodeType::File, NodeName::fromString('100x.txt'), NodePath::forChild($root->path, $root->id));

    $results = (new SuggestFilesByPrefix($repository))->execute('100%', null);

    expect(array_map(fn ($node) => $node->name, $results))->toBe(['100%.txt']);
});

test('a literal underscore is escaped and does not act as a single-character wildcard', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();
    $repository->create($root->id, NodeType::File, NodeName::fromString('a_b.txt'), NodePath::forChild($root->path, $root->id));
    $repository->create($root->id, NodeType::File, NodeName::fromString('axb.txt'), NodePath::forChild($root->path, $root->id));

    $results = (new SuggestFilesByPrefix($repository))->execute('a_b', null);

    expect(array_map(fn ($node) => $node->name, $results))->toBe(['a_b.txt']);
});

test('a literal backslash is escaped', function () {
    $repository = new InMemoryNodeRepository;
    $root = $repository->findRoot();
    $repository->create($root->id, NodeType::File, NodeName::fromString('a\\b.txt'), NodePath::forChild($root->path, $root->id));
    $repository->create($root->id, NodeType::File, NodeName::fromString('axb.txt'), NodePath::forChild($root->path, $root->id));

    $results = (new SuggestFilesByPrefix($repository))->execute('a\\b', null);

    expect(array_map(fn ($node) => $node->name, $results))->toBe(['a\\b.txt']);
});
