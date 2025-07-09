<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\KeyTransaction;
use App\Notifications\KeyOverdueNotification;
use Carbon\Carbon;

class NotifyKeyOverdue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'keys:notify-overdue {--hours=1 : Number of hours overdue before notification} {--force : Skip confirmation prompt for automated execution}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify users who have not returned their keys and are overdue.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $hours = $this->option('hours');
        $now = Carbon::now();
        $overdueThreshold = $now->copy()->subHours($hours);

        // Find overdue key transactions that haven't been notified yet
        $overdueTransactions = KeyTransaction::where('status', 'checked_out')
            ->where('expected_return_at', '<', $overdueThreshold)
            ->whereNull('overdue_notified_at')
            ->with(['user', 'key.resource', 'booking'])
            ->get();

        $count = $overdueTransactions->count();

        if ($count === 0) {
            $this->info("No overdue keys found older than {$hours} hour(s).");
            return 0;
        }

        $this->info("Found {$count} overdue key transaction(s):");

        foreach ($overdueTransactions as $transaction) {
            $this->line("- User: {$transaction->user->email} | Key: {$transaction->key->key_code} | Resource: {$transaction->key->resource->name} | Expected Return: {$transaction->expected_return_at->format('Y-m-d H:i:s')}");
        }

        // Ask for confirmation (skip if --force is used)
        if (!$this->option('force') && !$this->confirm("Do you want to notify these {$count} user(s) about their overdue keys?")) {
            $this->info("Operation cancelled.");
            return 0;
        }

        $notifiedCount = 0;
        foreach ($overdueTransactions as $transaction) {
            try {
                $transaction->user->notify(new KeyOverdueNotification($transaction));
                $transaction->overdue_notified_at = $now;
                $transaction->save();
                $notifiedCount++;
                $this->info("Notified user {$transaction->user->email} about overdue key {$transaction->key->key_code}.");
            } catch (\Exception $e) {
                $this->error("Failed to notify user {$transaction->user->email}: {$e->getMessage()}");
            }
        }

        $this->info("Successfully notified {$notifiedCount} user(s) about overdue keys.");
        return 0;
    }
} 