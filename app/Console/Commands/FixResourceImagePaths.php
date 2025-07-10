<?php

namespace App\Console\Commands;

use App\Models\Resource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixResourceImagePaths extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:resource-image-paths';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix resource image paths that contain temporary file paths';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for resources with temporary file paths...');

        // Find resources with temp paths
        $resourcesWithTempPaths = Resource::where('image', 'like', '%tmp%')
            ->orWhere('image', 'like', '%AppData%')
            ->orWhere('image', 'like', '%Local%')
            ->get();

        if ($resourcesWithTempPaths->count() === 0) {
            $this->info('No resources with temporary file paths found.');
            return 0;
        }

        $this->warn("Found {$resourcesWithTempPaths->count()} resource(s) with temporary file paths:");

        foreach ($resourcesWithTempPaths as $resource) {
            $this->line("- ID: {$resource->id}, Name: {$resource->name}");
            $this->line("  Current image path: {$resource->image}");
        }

        if ($this->confirm('Do you want to clear these temporary image paths?')) {
            $updated = Resource::where('image', 'like', '%tmp%')
                ->orWhere('image', 'like', '%AppData%')
                ->orWhere('image', 'like', '%Local%')
                ->update(['image' => null]);

            $this->info("Updated {$updated} resource(s) - image paths cleared.");
        } else {
            $this->info('No changes made.');
        }

        return 0;
    }
} 