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
     * DEBUG: Catch all update types (only fires for types with no specific handler).
     */
    public function onAny(array $update): void
    {
        $type = $update['_'] ?? 'unknown';
        // Only log non-noise update types
        if (!in_array($type, ['updateUserStatus', 'updateUserTyping', 'updateReadHistoryOutbox', 'updateChatUserTyping'])) {
            Log::info("MTProto Unhandled Update for Account " . self::$account_id, ['type' => $type]);
        }
    }

    public function onUpdateNewMessage(array $update): void
    {
        try {
            if (($update['message']['_'] ?? '') === 'messageService') {
                return;
            }

            $message = $update['message'];

            // Only handle incoming messages (out = false means received)
            if ($message['out'] ?? false) {
                return;
            }

            // Get numeric ID from update
            $from_id = $message['from_id']['user_id'] ?? null;
            if (!$from_id && isset($message['from_id']) && is_numeric($message['from_id'])) {
                $from_id = $message['from_id'];
            }
            
            if (!$from_id) {
                $from_id = $message['peer_id']['user_id'] ?? null;
                if (!$from_id && isset($message['peer_id']) && is_numeric($message['peer_id'])) {
                    $from_id = $message['peer_id'];
                }
            }

            if (!$from_id) {
                return;
            }

            $identifier = (string)$from_id;

            // TRY to resolve to a username to avoid split chats
            try {
                // getInfo is async in v8 EventHandler. We try to see if we can get username.
                // We use a short timeout or just a check to avoid hanging.
                $info = $this->getInfo($from_id);
                if (isset($info['User']['username']) && !empty($info['User']['username'])) {
                    $identifier = '@' . $info['User']['username'];
                } elseif (isset($info['User']['phone']) && !empty($info['User']['phone'])) {
                    $identifier = $info['User']['phone'];
                }
            } catch (\Throwable $e) {
                // If getInfo fails (likely due to async Fiber constraints in some environments),
                // we fall back to the numeric ID we already have.
                Log::debug("MTProto: getInfo failed for {$from_id}, using numeric ID.");
            }

            $messageText = $message['message'] ?? '';
            $messageTime = date('Y-m-d H:i:s', $message['date'] ?? time());

            // Avoid duplicates
            $exists = MtprotoMessage::where('account_id', self::$account_id)
                ->where('contact_identifier', $identifier)
                ->where('message', $messageText)
                ->where('message_time', $messageTime)
                ->exists();

            if (!$exists) {
                MtprotoMessage::create([
                    'user_id'            => self::$user_id,
                    'account_id'         => self::$account_id,
                    'contact_identifier' => $identifier,
                    'direction'          => 'in',
                    'message'            => $messageText,
                    'message_time'       => $messageTime,
                    'status'             => 'success',
                ]);
                Log::info("Captured incoming message for Account " . self::$account_id, [
                    'from'    => $identifier,
                    'message' => substr($messageText, 0, 50),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("MTProto EventHandler onUpdateNewMessage Error: " . $e->getMessage());
        }
    }

    public function onUpdateNewChannelMessage(array $update): void
    {
        $this->onUpdateNewMessage($update);
    }
}
