<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\MtprotoCampaign;
use App\Models\MtprotoAccount;
use App\Models\MtprotoMessage;
use App\Services\MTProtoServiceInterface;
use Illuminate\Support\Facades\Log;

class SendMTProtoCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaign_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($campaign_id)
    {
        $this->campaign_id = $campaign_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(MTProtoServiceInterface $mtproto_service)
    {
        $campaign = MtprotoCampaign::find($this->campaign_id);
        if (!$campaign || $campaign->status !== 'pending') {
            return;
        }

        $campaign->update(['status' => 'processing']);

        // Get the first active account for this user
        $account = MtprotoAccount::where('user_id', $campaign->user_id)
            ->where('status', '1')
            ->first();

        if (!$account) {
            $campaign->update(['status' => 'failed']);
            Log::error("MTProto Account not found for user " . $campaign->user_id);
            return;
        }

        $mtproto_service->setAccount($account);

        $contacts = $campaign->list->contacts;
        $template = $campaign->template;

        foreach ($contacts as $index => $contact) {
            try {
                // Personalize message
                $message = str_replace('{first_name}', $contact->first_name ?? 'there', $template->message);
                $identifier = $contact->username ?: $contact->phone;

                if (!$identifier) continue;

                $mtproto_service->sendMessage($identifier, $message);

                // Log outbox message
                MtprotoMessage::create([
                    'user_id' => $campaign->user_id,
                    'account_id' => $account->id,
                    'campaign_id' => $campaign->id,
                    'contact_identifier' => $identifier,
                    'direction' => 'out',
                    'message' => $message,
                    'status' => 'success',
                    'sent_via' => $account->proxy_host ?? 'Local Server IP',
                    'message_time' => now()
                ]);

                $campaign->increment('sent_count');

                // Randomized Throttling (Anti-Ban) - Only sleep if not the last contact
                if ($index < count($contacts) - 1) {
                    $base_delay = $campaign->interval_min * 60;
                    $random_offset = rand(-10, 30); // Add/subtract random seconds
                    $total_delay = max(10, $base_delay + $random_offset);

                    sleep($total_delay);
                }

            } catch (\Exception $e) {
                $campaign->increment('failed_count');
                Log::error("Failed to send MTProto DM to {$identifier}: " . $e->getMessage());

                // Log failed attempt
                MtprotoMessage::create([
                    'user_id' => $campaign->user_id,
                    'account_id' => $account->id,
                    'campaign_id' => $campaign->id,
                    'contact_identifier' => $identifier,
                    'direction' => 'out',
                    'message' => $message ?? $template->message,
                    'status' => 'failed',
                    'error' => substr($e->getMessage(), 0, 255),
                    'sent_via' => $account->proxy_host ?? 'Local Server IP',
                    'message_time' => now()
                ]);
                
                // If Peer Flood error, pause campaign
                if (strpos($e->getMessage(), 'FLOOD') !== false) {
                    $campaign->update(['status' => 'paused']);
                    break;
                }

                // If Session is unregistered/expired, pause and log failure
                if (strpos($e->getMessage(), 'AUTH_KEY_UNREGISTERED') !== false) {
                    $account->update(['status' => '0']); // Mark account as inactive
                    $campaign->update(['status' => 'paused']);
                    Log::critical("MTProto Session Expired for Account {$account->id}. Campaign Paused.");
                    break;
                }
            }
        }

        // Final status update - check fresh status in case it was paused inside the loop
        if ($campaign->fresh()->status !== 'paused') {
            $campaign->update(['status' => 'completed']);
        }
    }
}
