<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Str;

class FixUserUuids extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:fix-uuids';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add UUIDs to users that don\'t have them';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $usersWithoutUuid = User::whereNull('uuid')->get();
        
        if ($usersWithoutUuid->count() === 0) {
            $this->info('All users already have UUIDs.');
            return 0;
        }

        $this->info("Found {$usersWithoutUuid->count()} users without UUIDs. Adding UUIDs...");

        $bar = $this->output->createProgressBar($usersWithoutUuid->count());
        $bar->start();

        foreach ($usersWithoutUuid as $user) {
            $user->uuid = (string) Str::uuid();
            $user->save();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Successfully added UUIDs to all users.');

        return 0;
    }
} 