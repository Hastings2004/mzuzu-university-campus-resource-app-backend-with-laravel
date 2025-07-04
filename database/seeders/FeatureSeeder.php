<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Feature;

class FeatureSeeder extends Seeder
{
    public function run()
    {
        $features = [
            'projector',
            'whiteboard',
            'computer',
            'power_outlet',
            'audio_system',
            'video_conferencing',
            'air_conditioning',
            'wifi',
        ];

        foreach ($features as $feature) {
            Feature::firstOrCreate(['name' => $feature]);
        }
    }
} 