<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;

class DeleteUnverifiedUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:delete-unverified {--hours=1 : Number of hours to wait before deletion} {--force : Skip confirmation prompt for automated execution}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete users who have not verified their email after the specified number of hours (default: 1 hour)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $hours = $this->option('hours');
        $cutoffTime = Carbon::now()->subHours($hours);

        // Find users who haven't verified their email and were created more than the specified hours ago
        $unverifiedUsers = User::whereNull('email_verified_at')
            ->where('created_at', '<', $cutoffTime)
            ->get();

        $count = $unverifiedUsers->count();

        if ($count === 0) {
            $this->info("No unverified users found older than {$hours} hour(s).");
            return 0;
        }

        // Display the users that will be deleted
        $this->warn("Found {$count} unverified user(s) older than {$hours} hour(s):");
        
        foreach ($unverifiedUsers as $user) {
            $this->line("- {$user->email} (created: {$user->created_at->format('Y-m-d H:i:s')})");
        }

        // Ask for confirmation (skip if --force is used)
        if (!$this->option('force') && !$this->confirm("Do you want to delete these {$count} unverified user(s)?")) {
            $this->info("Operation cancelled.");
            return 0;
        }

        // Delete the users
        $deletedCount = 0;
        foreach ($unverifiedUsers as $user) {
            try {
                $user->delete();
                $deletedCount++;
                $this->line("Deleted user: {$user->email}");
            } catch (\Exception $e) {
                $this->error("Failed to delete user {$user->email}: {$e->getMessage()}");
            }
        }

        $this->info("Successfully deleted {$deletedCount} unverified user(s).");
        return 0;
    }
} 