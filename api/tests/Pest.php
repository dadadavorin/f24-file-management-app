<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');

uses()->group('domain')->in('Unit/Domain');
uses()->group('application')->in('Unit/Application');
uses(RefreshDatabase::class)->group('persistence')->in('Feature/Persistence');
