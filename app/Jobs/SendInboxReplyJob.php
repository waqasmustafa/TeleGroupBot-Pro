<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\MtprotoMessage;
use Illuminate\Support\Facades\Log;

class SendInboxReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $timeout = 120;

    protected $accountId;
    protected $userId;
    protected $identifier;
    protected $message;
    protected $messageDbId;

    public function __construct($accountId, $userId, $identifier, $message, $messageDbId)
    {
        $this->accountId   = $accountId;
        $this->userId      = $userId;
        $this->identifier  = $identifier;
        $this->message     = $message;
        $this->messageDbId = $messageDbId;
    }

    public function handle()
    {
        $msg = MtprotoMessage::find($this->messageDbId);
        if (!$msg) {
            Log::error("SendInboxReplyJob: Message record missing", ['id' => $this->messageDbId]);
            return;
        }

        if ($msg->status !== 'pending') {
            Log::info("SendInboxReplyJob: Skipping, message status is {$msg->status}", ['id' => $this->messageDbId]);
            return;
        }

        // We spawn a fresh PHP CLI process for mtproto:send.
        // This bypasses the MadelineProto IPC issue on Windows/XAMPP where
        // the queue worker cannot connect to a session opened by Apache.
        // A fresh process becomes its own MadelineProto server — no IPC needed.

        $phpBin   = $this->getPhpBinary();
        $artisan  = base_path('artisan');
        
        // Escape arguments for the shell
        $accountId  = escapeshellarg($this->accountId);
        $identifier = escapeshellarg($this->identifier);
        $msgId      = escapeshellarg($this->messageDbId);

        $cmd = "{$phpBin} {$artisan} mtproto:send {$accountId} {$identifier} {$msgId} 2>&1";

        Log::info("SendInboxReplyJob: Spawning CLI process", ['cmd' => $cmd, 'msg_id' => $this->messageDbId]);

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        $outputStr = implode("\n", $output);
        Log::info("SendInboxReplyJob: CLI process finished", ['exit_code' => $exitCode, 'output' => $outputStr]);

        if ($exitCode !== 0) {
            // Check if it's already been successful (maybe listener picked it up during exec)
            $msg->refresh();
            if ($msg->status === 'success') return;

            MtprotoMessage::where('id', $this->messageDbId)->update(['status' => 'failed']);
            throw new \RuntimeException("mtproto:send failed (exit {$exitCode}): {$outputStr}");
        }
    }

    private function getPhpBinary(): string
    {
        // On Windows XAMPP, PHP is in the xampp folder
        if (PHP_OS_FAMILY === 'Windows') {
            $candidates = [
                PHP_BINARY,                          // Current PHP binary
                'C:\\xampp\\php\\php.exe',
                'php',
            ];
            foreach ($candidates as $bin) {
                if (file_exists($bin) || $bin === 'php') {
                    return escapeshellarg($bin);
                }
            }
        }
        // Linux/Mac: use PHP_BINARY (or fall back to 'php')
        return escapeshellarg(PHP_BINARY ?: 'php');
    }
}
