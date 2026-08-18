<?php

use App\Domain\FileSystem\Repository\NodeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');

uses()->group('domain')->in('Unit/Domain');
uses()->group('application')->in('Unit/Application');
uses(RefreshDatabase::class)->group('persistence')->in('Feature/Persistence');
uses(RefreshDatabase::class)->group('http')->in('Feature/Http');
uses(RefreshDatabase::class)->group('search')->in('Feature/Search');

function repository(): NodeRepository
{
    return app(NodeRepository::class);
}
