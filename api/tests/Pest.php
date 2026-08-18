<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');

uses()->group('domain')->in('Unit/Domain');
uses(RefreshDatabase::class)->group('persistence')->in('Feature/Persistence');
