<?php

declare(strict_types=1);

namespace Tests\Feature\Airs;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ConnectorRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_connector_routes_and_tables_are_gone(): void
    {
        $this->assertFalse(Route::has('airs.index'));
        $this->assertFalse(Schema::hasTable('integration.airs_reviews'));
        $this->assertFalse(Schema::hasTable('integration.airs_feedback'));
        $this->assertTrue(Schema::hasTable('core.accepted_submissions'));
        $this->assertArrayNotHasKey('airs:sync', Artisan::all());
        $this->assertArrayNotHasKey('airs:check', Artisan::all());
    }
}
