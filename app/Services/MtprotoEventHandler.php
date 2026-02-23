<?php

namespace App\Services;

use danog\MadelineProto\EventHandler;
use danog\Loop\PeriodicLoop;
use App\Models\MtprotoMessage;
use Illuminate\Support\Facades\Log;

class MtprotoEventHandler extends EventHandler
{
    public static $user_id;
    public static $account_id;

    public function onStart(): void
    {
        Log::info("EventHandler started for Account " . self::$account_id);
        
        // Polling loop for outgoing messages (Windows compatibility hack)
        $loop = new PeriodicLoop(function () {
            try {
                $pending = MtprotoMessage::where('account_id', self::$account_id)
                    ->where('direction', 'out')
                    ->where('status', 'pending')
                    ->get();

                foreach ($pending as $msg) {
                    Log::info("Listener processing pending reply to " . $msg->contact_identifier);
                    
                    // Handle phone numbers
                    $toId = $msg->contact_identifier;
                    if (is_numeric($toId) && strpos($toId, '+') !== 0) {
                        $toId = '+' . $toId;
                    }

                    $this->messages->sendMessage([
                        'peer' => $toId,
                        'message' => $msg->message,
                    ]);
                    
                    $msg->update(['status' => 'success']);
                }
            } catch (\Exception $e) {
                Log::error("Listener Polling Error: " . $e->getMessage());
            }
        }, 1.0); // Check every 1 second
        
        $loop->start();
    }

    public function onUpdateNewMessage(array $update): void
    {
        if ($update['message']['_'] === 'messageService') {
            return;
        }

        $message = $update['message'];

        // Only handle incoming messages (out = false)
        if ($message['out'] ?? false) {
            return;
        }

        $from_id = $message['from_id']['user_id'] ?? null;
        if (!$from_id) return;

        try {
            // Fetch peer info to get username/phone
            $info = $this->getInfo($from_id);
            $identifier = $info['User']['username'] ?? $info['User']['phone'] ?? (string)$from_id;

            // Check if message already exists to avoid duplicates (though loop() handles state)
            $exists = MtprotoMessage::where('account_id', self::$account_id)
                ->where('contact_identifier', $identifier)
                ->where('message', $message['message'] ?? '')
                ->where('message_time', date('Y-m-d H:i:s', $message['date']))
                ->exists();

            if (!$exists) {
                MtprotoMessage::create([
                    'user_id' => self::$user_id,
                    'account_id' => self::$account_id,
                    'contact_identifier' => $identifier,
                    'direction' => 'in',
                    'message' => $message['message'] ?? '',
                    'message_time' => date('Y-m-d H:i:s', $message['date']),
                    'status' => 'success'
                ]);
                Log::info("Captured incoming message for Account " . self::$account_id, ['from' => $identifier]);
            }
        } catch (\Exception $e) {
            Log::error("MTProto EventHandler Error: " . $e->getMessage());
        }
    }
}
