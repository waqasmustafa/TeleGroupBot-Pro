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
        if (!in_array($type, ['updateUserStatus', 'updateUserTyping'])) {
            Log::info("MTProto Unhandled Update for Account " . self::$account_id, ['type' => $type]);
        }
    }

    public function onUpdateNewMessage(array $update): void
    {
        try {
            // Log that we entered this method (debug)
            Log::info("MTProto onUpdateNewMessage called for Account " . self::$account_id, [
                'msg_type' => $update['message']['_'] ?? 'unknown',
                'out'      => $update['message']['out'] ?? null,
            ]);

            if (($update['message']['_'] ?? '') === 'messageService') {
                return;
            }

            $message = $update['message'];

            // Only handle incoming messages (out = false means received)
            if ($message['out'] ?? false) {
                return;
            }

            // Get identifier from from_id WITHOUT calling getInfo() (avoids async issues)
            // Handle both legacy array structure and new direct numeric IDs
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
                Log::warning("MTProto: Could not determine from_id", ['update' => json_encode($message)]);
                return;
            }

            // Use user_id as identifier (can be enriched later)
            $identifier = (string)$from_id;

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
            // Catch ALL errors (not just \Exception) since amphp can throw Errors too
            Log::error("MTProto EventHandler onUpdateNewMessage Error: " . $e->getMessage(), [
                'class' => get_class($e),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);
        }
    }

    public function onUpdateNewChannelMessage(array $update): void
    {
        $this->onUpdateNewMessage($update);
    }
}
