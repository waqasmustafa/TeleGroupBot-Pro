<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MtprotoAccount;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

class MTProtoManagerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mtproto:manager 
                            {--check-interval=60 : Seconds between DB checks for new accounts} 
                            {--php-path= : Custom path to PHP binary (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically manage and scale MTProto listeners for all active accounts';

    /**
     * Array to keep track of running processes
     * [account_id => Process]
     */
    protected $processes = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("MTProto Manager started. Monitoring active accounts...");
        Log::info("MTProto Manager: Initializing...");

        $checkInterval = (int) $this->option('check-interval');

        while (true) {
            $this->manageProcesses();
            
            // Wait for next check
            sleep($checkInterval);
        }
    }

    protected function manageProcesses()
    {
        // Detect PHP binary path
        $phpPath = $this->option('php-path') ?: (PHP_BINARY ?: 'php');

        // 1. Get all active accounts from DB
        $activeAccounts = MtprotoAccount::where('status', '1')->get();
        $activeIds = $activeAccounts->pluck('id')->toArray();

        // 2. Stop processes for accounts that are no longer active
        foreach ($this->processes as $id => $process) {
            if (!in_array($id, $activeIds)) {
                $this->warn("Account $id is no longer active. Stopping listener...");
                $process->stop();
                unset($this->processes[$id]);
            }
        }

        // 3. Start or restart processes for active accounts
        foreach ($activeAccounts as $account) {
            $id = $account->id;

            // If process not running or has terminated, start it
            if (!isset($this->processes[$id]) || !$this->processes[$id]->isRunning()) {
                if (isset($this->processes[$id])) {
                    $exitCode = $this->processes[$id]->getExitCode();
                    $this->error("Listener for Account $id (Phone: {$account->phone}) stopped unexpectedly with exit code $exitCode. Restarting...");
                    Log::error("MTProto Manager: Listener for Account $id crashed. Restarting.", ['exit_code' => $exitCode]);
                } else {
                    $this->info("Starting new listener for Account $id (Phone: {$account->phone})");
                    Log::info("MTProto Manager: Starting listener for Account $id.");
                }

                $process = new Process([$phpPath, 'artisan', 'mtproto:listen', (string)$id]);
                $process->setWorkingDirectory(base_path());
                $process->setTimeout(null); 
                
                $process->start();
                
                $this->processes[$id] = $process;
            }
        }

        // 4. Print status
        $runningCount = count(array_filter($this->processes, fn($p) => $p->isRunning()));
        $this->info("[" . date('H:i:s') . "] Running Listeners: $runningCount / Total Active Accounts: " . count($activeAccounts));
    }
}
