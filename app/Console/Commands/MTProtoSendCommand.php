<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MtprotoAccount;
use App\Models\MtprotoMessage;
use App\Services\MTProtoService;
use Illuminate\Support\Facades\Log;

class MTProtoSendCommand extends Command
{
    protected $signature = 'mtproto:send {account_id} {identifier} {message_id}';
    protected $description = 'Send a single inbox reply via MTProto (IPC-free CLI process)';

    public function handle(MTProtoService $mtproto)
    {
        $accountId  = $this->argument('account_id');
        $identifier = $this->argument('identifier');
        $messageId  = $this->argument('message_id');

        $account = MtprotoAccount::find($accountId);
        if (!$account) {
            $this->error("Account #{$accountId} not found.");
            return 1;
        }

        // Get the message text from DB
        $msgRecord = MtprotoMessage::find($messageId);
        if (!$msgRecord) {
            $this->error("Message record #{$messageId} not found.");
            return 1;
        }

        $this->info("Sending message to {$identifier} via account #{$accountId}...");

        try {
            $result = $mtproto->setAccount($account)->sendMessage($identifier, $msgRecord->message);
            
            $telegram_id = $result['id'] ?? null;
            
            MtprotoMessage::where('id', $messageId)->update([
                'status' => 'success',
                'telegram_message_id' => $telegram_id
            ]);

            $this->info("Message sent successfully. Telegram ID: " . ($telegram_id ?? 'N/A'));
            Log::info("mtproto:send - Message #{$messageId} sent to {$identifier}, Telegram ID: {$telegram_id}");
            return 0;
        } catch (\Exception $e) {
            MtprotoMessage::where('id', $messageId)->update(['status' => 'failed']);
            $this->error("Failed: " . $e->getMessage());
            Log::error("mtproto:send - Failed to send #{$messageId}: " . $e->getMessage());
            return 1;
        }
    }
}
