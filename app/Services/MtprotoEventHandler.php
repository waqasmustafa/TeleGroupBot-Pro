<?php

declare(strict_types=1);

namespace App\Services;

use danog\MadelineProto\EventHandler;
use App\Models\MtprotoMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
                // getInfo is async in v8 EventHandler.
                $info = $this->getInfo($from_id);
                if (isset($info['User']['username']) && !empty($info['User']['username'])) {
                    $identifier = '@' . $info['User']['username'];
                } elseif (isset($info['User']['phone']) && !empty($info['User']['phone'])) {
                    $identifier = $info['User']['phone'];
                }
            } catch (\Throwable $e) {
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

                // SYNC NOTIFICATIONS:
                // 1. Notify the account owner
                // 2. Notify all Admins
                $this->createSystemNotification($identifier, $messageText);
            }
        } catch (\Throwable $e) {
            Log::error("MTProto EventHandler onUpdateNewMessage Error: " . $e->getMessage());
        }
    }

    /**
     * Helper to create system notifications for the CRM header.
     */
    protected function createSystemNotification(string $sender, string $text): void
    {
        try {
            $preview = mb_substr($text, 0, 100);
            $preview = !empty($preview) ? $preview : "[Media/Document]";
            
            $notifData = [
                'title'       => "New Telegram Message",
                'description' => "New message from {$sender}: {$preview}",
                'created_at'  => date('Y-m-d H:i:s'),
                'is_seen'     => '0',
                'color_class' => 'bg-info',
                'icon'        => 'fas fa-envelope',
                'published'   => '1',
                'linkable'    => '1',
                'custom_link' => route('mtproto.inbox'),
            ];

            // 1. Target Account Owner
            $targets = [self::$user_id];

            // 2. Target all Admins
            $adminIds = User::where('user_type', 'Admin')->pluck('id')->toArray();
            foreach ($adminIds as $aid) {
                if (!in_array($aid, $targets)) {
                    $targets[] = $aid;
                }
            }

            // Insert records for all targets
            foreach ($targets as $targetId) {
                $item = $notifData;
                $item['user_id'] = $targetId;
                DB::table('notifications')->insert($item);
            }

        } catch (\Throwable $e) {
            Log::error("MTProto createSystemNotification Error: " . $e->getMessage());
        }
    }

    public function onUpdateNewChannelMessage(array $update): void
    {
        $this->onUpdateNewMessage($update);
    }
}
