<?php

namespace Database\Seeders;

use App\Models\Gis\GisLayer;
use Illuminate\Database\Seeder;

class GisLayerSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('gis.layers', []) as $layer) {
            $id = $layer['id'];
            unset($layer['id']);

            // Re-seeding must keep operator edits (match_attributes, where_clause, active snapshot).
            $model = GisLayer::query()->find($id);
            if ($model === null) {
                GisLayer::query()->create(['id' => $id] + $layer);
            } else {
                $model->update(array_intersect_key($layer, array_flip(['label', 'kind', 'source_url', 'sort_order'])));
            }
        }
    }
}
