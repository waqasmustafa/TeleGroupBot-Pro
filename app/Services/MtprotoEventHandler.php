<?php

declare(strict_types=1);

namespace App\Services;

use danog\MadelineProto\EventHandler;
use App\Models\MtprotoMessage;
use Illuminate\Support\Facades\Log;

class MtprotoEventHandler extends EventHandler
{
    public static $user_id;
    public static $account_id;
    public static $api_id;
    public static $api_hash;

    public function onStart(): void
    {
        Log::info("EventHandler started for Account " . self::$account_id);
    }

    /**
     * DEBUG: Catch ALL update types to log what is actually being received.
     * Remove this method once the correct update type is identified.
     */
    public function onAny(array $update): void
    {
        $type = $update['_'] ?? 'unknown';
        Log::info("MTProto Raw Update received for Account " . self::$account_id, [
            'type'    => $type,
            'out'     => $update['message']['out'] ?? null,
            'msg'     => isset($update['message']['message'])
                         ? substr($update['message']['message'], 0, 50)
                         : null,
        ]);
    }

    public function onUpdateNewMessage(array $update): void
    {
        if (($update['message']['_'] ?? '') === 'messageService') {
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

            // Check if message already exists to avoid duplicates
            $exists = MtprotoMessage::where('account_id', self::$account_id)
                ->where('contact_identifier', $identifier)
                ->where('message', $message['message'] ?? '')
                ->where('message_time', date('Y-m-d H:i:s', $message['date']))
                ->exists();

            if (!$exists) {
                MtprotoMessage::create([
                    'user_id'            => self::$user_id,
                    'account_id'         => self::$account_id,
                    'contact_identifier' => $identifier,
                    'direction'          => 'in',
                    'message'            => $message['message'] ?? '',
                    'message_time'       => date('Y-m-d H:i:s', $message['date']),
                    'status'             => 'success'
                ]);
                Log::info("Captured incoming message for Account " . self::$account_id, ['from' => $identifier]);
            }
        } catch (\Exception $e) {
            Log::error("MTProto EventHandler Error: " . $e->getMessage());
        }
    }

    public function onUpdateNewChannelMessage(array $update): void
    {
        // Delegate channel messages to same logic as direct messages
        $this->onUpdateNewMessage($update);
    }
}
