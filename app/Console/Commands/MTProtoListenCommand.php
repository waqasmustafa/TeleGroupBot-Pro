<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MtprotoAccount;
use App\Services\MTProtoServiceInterface;

class MTProtoListenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mtproto:listen {account_id : The ID of the MTProto account to listen for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start real-time message listening for a specific MTProto account';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(MTProtoServiceInterface $mtproto)
    {
        $accountId = $this->argument('account_id');
        $account = MtprotoAccount::where('id', $accountId)->first();

        if (!$account) {
            $this->error("Account not found!");
            return 1;
        }

        $this->info("Initializing Live Listener for Account: {$account->phone} (ID: {$account->id})");
        
        try {
            $mtproto->startListener($account);
        } catch (\Exception $e) {
            $this->error("Listener Error: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
