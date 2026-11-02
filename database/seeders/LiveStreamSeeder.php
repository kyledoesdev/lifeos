<?php

namespace Database\Seeders;

use App\Models\LiveStream;
use App\Models\SocialMediaPost;
use App\Models\StreamCategoryRule;
use Illuminate\Database\Seeder;

class LiveStreamSeeder extends Seeder
{
    public function run(): void
    {
        StreamCategoryRule::factory()->minecraft()->create();

        $minecraft = LiveStream::factory()->ended()->create([
            'started_at' => now()->subDays(3),
        ]);

        SocialMediaPost::factory()->for($minecraft, 'postable')->create();

        LiveStream::factory()->softwareDevelopment()->ended()->create([
            'started_at' => now()->subDay(),
        ]);
    }
}
