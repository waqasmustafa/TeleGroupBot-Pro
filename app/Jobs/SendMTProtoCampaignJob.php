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
use App\Events\MtprotoRealtimeEvent;
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

        // BROADCAST STATUS CHANGE:
        MtprotoRealtimeEvent::dispatch($campaign->user_id, 'campaign', [
            'id' => $campaign->id,
            'status' => 'processing',
            'sent_count' => $campaign->sent_count,
            'failed_count' => $campaign->failed_count
        ]);

        // Load selected accounts and templates
        $accounts = [];
        if (!empty($campaign->account_ids)) {
            $accounts = MtprotoAccount::whereIn('id', $campaign->account_ids)
                ->where('status', '1')
                ->get();
        }

        // Fallback for single account
        if ($accounts->isEmpty() && $campaign->account_id) {
            $acc = MtprotoAccount::where('id', $campaign->account_id)->where('status', '1')->first();
            if ($acc) $accounts = collect([$acc]);
        }

        if ($accounts->isEmpty()) {
            $campaign->update(['status' => 'failed']);
            Log::error("No active MTProto Accounts found for campaign " . $campaign->id);
            return;
        }

        $templates = [];
        if (!empty($campaign->template_ids)) {
            $templates = \App\Models\MtprotoTemplate::whereIn('id', $campaign->template_ids)->get();
        }

        // Fallback for single template
        if ($templates->isEmpty() && $campaign->template_id) {
            $temp = \App\Models\MtprotoTemplate::find($campaign->template_id);
            if ($temp) $templates = collect([$temp]);
        }

        if ($templates->isEmpty()) {
            $campaign->update(['status' => 'failed']);
            Log::error("No templates found for campaign " . $campaign->id);
            return;
        }

        $contacts = $campaign->list->contacts;
        $total_accounts = $accounts->count();
        $errored_account_ids = []; // Tracks accounts that hit limits during this run

        foreach ($contacts as $index => $contact) {
            // Find next available account using Round-Robin
            $account = null;
            for ($attempt = 0; $attempt < $total_accounts; $attempt++) {
                $acc_idx = ($index + $attempt) % $total_accounts;
                $candidate = $accounts[$acc_idx];
                
                if (!in_array($candidate->id, $errored_account_ids)) {
                    $account = $candidate;
                    break;
                }
            }

            if (!$account) {
                Log::error("All selected accounts for campaign {$campaign->id} have hit limits. Pausing.");
                $campaign->update(['status' => 'paused']);
                break;
            }

            try {
                // Select a random template
                $template = $templates->random();

                // Personalize message
                $message = str_replace('{first_name}', $contact->first_name ?? 'there', $template->message);
                $identifier = $contact->username ?: $contact->phone;

                if (!$identifier) continue;

                $mtproto_service->setAccount($account);
                $mtproto_service->sendMessage($identifier, $message);

                // Log outbox message
                MtprotoMessage::create([
                    'user_id'            => $campaign->user_id,
                    'account_id'         => $account->id,
                    'campaign_id'        => $campaign->id,
                    'contact_identifier' => $identifier,
                    'direction'          => 'out',
                    'message'            => $message,
                    'status'             => 'success',
                    'sent_via'           => $account->phone . ' (' . ($account->proxy_host ?? 'Local') . ')',
                    'message_time'       => now()
                ]);

                $campaign->increment('sent_count');

                // BROADCAST PROGRESS:
                MtprotoRealtimeEvent::dispatch($campaign->user_id, 'campaign', [
                    'id' => $campaign->id,
                    'status' => 'processing',
                    'sent_count' => $campaign->sent_count,
                    'failed_count' => $campaign->failed_count
                ]);

                // Randomized Throttling (Anti-Ban) - Only sleep if not the last contact
                if ($index < count($contacts) - 1) {
                    $base_delay = $campaign->interval_min * 60;
                    $random_offset = rand(-10, 30);
                    $total_delay = max(10, $base_delay + $random_offset);
                    sleep($total_delay);
                }

            } catch (\Exception $e) {
                $campaign->increment('failed_count');
                Log::error("Failed to send MTProto DM from Account {$account->id} to {$identifier}: " . $e->getMessage());

                // If Peer Flood or Auth error, don't use this account for subsequent messages in this run
                if (strpos($e->getMessage(), 'FLOOD') !== false || strpos($e->getMessage(), 'AUTH_KEY_UNREGISTERED') !== false) {
                    $errored_account_ids[] = $account->id;
                    if (strpos($e->getMessage(), 'AUTH_KEY_UNREGISTERED') !== false) {
                        $account->update(['status' => '0']);
                    }
                    // Rewind loop index to retry this contact with next account
                    $index--;
                    continue;
                }

                // Normal failure (e.g. invalid username) - log and move to next contact
                MtprotoMessage::create([
                    'user_id'            => $campaign->user_id,
                    'account_id'         => $account->id,
                    'campaign_id'        => $campaign->id,
                    'contact_identifier' => $identifier,
                    'direction'          => 'out',
                    'message'            => $message ?? $template->message,
                    'status'             => 'failed',
                    'error'              => substr($e->getMessage(), 0, 255),
                    'sent_via'           => $account->phone,
                    'message_time'       => now()
                ]);
            }
        }

        // Final status update - check fresh status in case it was paused inside the loop
        if ($campaign->fresh()->status !== 'paused') {
            $campaign->update(['status' => 'completed']);
            
            // BROADCAST COMPLETION:
            MtprotoRealtimeEvent::dispatch($campaign->user_id, 'campaign', [
                'id' => $campaign->id,
                'status' => 'completed',
                'sent_count' => $campaign->sent_count,
                'failed_count' => $campaign->failed_count
            ]);
        }
    }
}
