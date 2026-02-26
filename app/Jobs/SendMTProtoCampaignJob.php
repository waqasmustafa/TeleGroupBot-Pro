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
    public $timeout = 3600; // Increase timeout to 1 hour to handle long sleep intervals

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

        if ($accounts->isEmpty()) {
            $campaign->update(['status' => 'failed']);
            Log::error("No active MTProto Accounts found for campaign " . $campaign->id);
            return;
        }

        $templates = [];
        if (!empty($campaign->template_ids)) {
            $templates = \App\Models\MtprotoTemplate::whereIn('id', $campaign->template_ids)->get();
        }

        if ($templates->isEmpty()) {
            $campaign->update(['status' => 'failed']);
            Log::error("No templates found for campaign " . $campaign->id);
            return;
        }

        $contacts = $campaign->list->contacts;
        $total_contacts = $contacts->count();
        $total_accounts = $accounts->count();
        $errored_account_ids = []; // Tracks accounts that hit limits during this run
        $service_instances = []; // Cache for MTProtoService instances

        $index = 0;
        while ($index < $total_contacts) {
            $contact = $contacts[$index];
            
            // Check if campaign was paused externally
            if ($campaign->fresh()->status === 'paused') break;

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
                Log::error("All selected accounts for campaign {$campaign->id} have hit limits or auth errors. Pausing.");
                $campaign->update(['status' => 'paused']);
                break;
            }

            // Get or create service instance
            if (!isset($service_instances[$account->id])) {
                $service_instances[$account->id] = clone $mtproto_service;
                $service_instances[$account->id]->setAccount($account);
            }
            $active_service = $service_instances[$account->id];

            $identifier = $contact->username ?: $contact->phone;
            if (!$identifier) {
                $index++;
                continue;
            }

            try {
                // Select a random template
                $template = $templates->random();

                // Personalize message
                $message = str_replace('{first_name}', $contact->first_name ?? 'there', $template->message);

                $active_service->sendMessage($identifier, $message);

                // Log success
                MtprotoMessage::create([
                    'user_id'            => $campaign->user_id,
                    'account_id'         => $account->id,
                    'campaign_id'        => $campaign->id,
                    'contact_identifier' => $identifier,
                    'direction'          => 'out',
                    'message'            => $message,
                    'status'             => 'success',
                    'sent_via'           => $account->phone,
                    'error'              => "Template ID: " . $template->id, // Store template ID here for debugging
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

                // Randomized Throttling (Anti-Ban)
                if ($index < $total_contacts - 1) {
                    $base_delay = $campaign->interval_min * 60;
                    $random_offset = rand(-10, 30);
                    $total_delay = max(10, $base_delay + $random_offset);
                    sleep($total_delay);
                }

                $index++; // Move to next contact ONLY after success

            } catch (\Exception $e) {
                $error_msg = $e->getMessage();
                $campaign->increment('failed_count');
                Log::error("MTProto Campaign Error (Acc: {$account->id}, To: {$identifier}): " . $error_msg);

                // Log the failure to DB
                MtprotoMessage::create([
                    'user_id'            => $campaign->user_id,
                    'account_id'         => $account->id,
                    'campaign_id'        => $campaign->id,
                    'contact_identifier' => $identifier,
                    'direction'          => 'out',
                    'message'            => $message ?? 'N/A',
                    'status'             => 'failed',
                    'error'              => substr($error_msg, 0, 255),
                    'sent_via'           => $account->phone,
                    'message_time'       => now()
                ]);

                // If Critical error (Auth or Flood), disable account and DON'T increment index to retry
                if (strpos($error_msg, 'FLOOD') !== false || strpos($error_msg, 'AUTH_KEY_UNREGISTERED') !== false || strpos($error_msg, 'ACCOUNT_KEY_INVALID') !== false) {
                    $errored_account_ids[] = $account->id;
                    if (strpos($error_msg, 'AUTH_KEY') !== false) {
                        $account->update(['status' => '0']);
                    }
                    // Wait a bit before retry with next account
                    sleep(5);
                } else {
                    // Normal failure (e.g. invalid username), just move on
                    $index++;
                }
            }
        }

        // Final status update
        $campaign = $campaign->fresh();
        if ($campaign->status !== 'paused') {
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
