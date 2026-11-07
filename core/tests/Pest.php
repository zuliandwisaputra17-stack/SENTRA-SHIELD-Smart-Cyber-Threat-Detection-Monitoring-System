<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Test Feature memakai database MySQL `sentra_test` (lihat phpunit.xml) dan
| di-reset setiap test.
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
