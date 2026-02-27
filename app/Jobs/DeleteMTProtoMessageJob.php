<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\MtprotoMessage;
use App\Models\MtprotoAccount;
use App\Services\MTProtoServiceInterface;
use App\Events\MtprotoRealtimeEvent;
use Illuminate\Support\Facades\Log;

class DeleteMTProtoMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $message_id;
    protected $revoke;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($message_id, $revoke = true)
    {
        $this->message_id = $message_id;
        $this->revoke = $revoke;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(MTProtoServiceInterface $mtproto_service)
    {
        $msg = MtprotoMessage::find($this->message_id);
        if (!$msg) return;

        // If it's a remote deletion (Delete for Everyone)
        if ($this->revoke && $msg->telegram_message_id) {
            $account = MtprotoAccount::find($msg->account_id);
            if ($account && $account->status == '1') {
                try {
                    $mtproto_service->setAccount($account);
                    $mtproto_service->deleteMessages([$msg->telegram_message_id], true);
                    Log::info("MTProto: Message {$msg->telegram_message_id} deleted from Telegram for Everyone.");
                } catch (\Exception $e) {
                    Log::error("MTProto: Failed to delete message from Telegram: " . $e->getMessage());
                    // We still proceed to delete locally if it was requested
                }
            }
        }

        // Broadcast to update UI in real-time
        broadcast(new MtprotoRealtimeEvent($msg->user_id, 'message-deleted', [
            'message_id' => $msg->id,
            'contact_identifier' => $msg->contact_identifier,
            'account_id' => $msg->account_id
        ]));

        // Delete from local DB
        $msg->delete();
    }
}
