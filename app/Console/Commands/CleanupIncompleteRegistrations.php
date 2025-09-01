<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CleanupIncompleteRegistrations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:cleanup-incomplete {--hours=24 : Hours after which incomplete registrations should be deleted}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up incomplete user registrations older than specified hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = (int) $this->option('hours');

        $this->info("Cleaning up incomplete registrations older than {$hours} hours...");

        $deletedCount = User::where('password', null)
            ->where('status', 'inactive')
            ->where('created_at', '<', now()->subHours($hours))
            ->count();

        User::where('password', null)
            ->where('status', 'inactive')
            ->where('created_at', '<', now()->subHours($hours))
            ->delete();

        $this->info("Successfully deleted {$deletedCount} incomplete registrations.");

        return 0;
    }
}
