<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use App\Models\MtprotoAccount;

class MTProtoService implements MTProtoServiceInterface
{
    protected $MadelineProto;
    protected $account;
    protected $session_dir;

    private function includeMadeline()
    {
        if (defined('MADELINE_PROTO_V8_LOADED')) {
            return;
        }

        // Use the isolated MadelineProto v8 autoloader with output buffering to suppress echoes on Windows
        // We load it here ONLY when needed to avoid global namespace conflicts with Laravel's dependencies
        ob_start();
        require_once base_path('app/Library/MadelineProto/vendor/autoload.php');
        ob_end_clean();

        define('MADELINE_PROTO_V8_LOADED', true);
    }

    public function __construct()
    {
        $this->session_dir = storage_path('app/mtproto/');
        if (!file_exists($this->session_dir)) {
            mkdir($this->session_dir, 0777, true);
        }
    }

    private function getSettings($api_id = null, $api_hash = null)
    {
        $settings = new \danog\MadelineProto\Settings();
        
        // Suppress browser output (crucial for Windows/XAMPP/CLI)
        $settings->getLogger()->setType(\danog\MadelineProto\Logger::FILE_LOGGER);
        $settings->getLogger()->setExtra(storage_path('logs/madeline.log'));
        
        // Connection settings (v8 compatible)
        $connectionSettings = new \danog\MadelineProto\Settings\Connection();
        $connectionSettings->setIpv6(false); 
        $connectionSettings->setUseDoH(true); 
        $connectionSettings->setObfuscated(true);
        $connectionSettings->setProtocol(\danog\MadelineProto\Stream\MTProtoTransport\IntermediatePaddedStream::class);
        $settings->setConnection($connectionSettings);
        
        $appInfo = new \danog\MadelineProto\Settings\AppInfo();
        $appInfo->setDeviceModel('PC 64bit');
        $appInfo->setSystemVersion('Windows 10');
        $appInfo->setAppVersion('4.16.1');
        $appInfo->setLangCode('en');
        
        if ($api_id && $api_hash) {
            $appInfo->setApiId((int)$api_id);
            $appInfo->setApiHash($api_hash);
        }
        $settings->setAppInfo($appInfo);
        
        return $settings;
    }

    public function setAccount($account)
    {
        $this->includeMadeline();
        $this->account = $account;
        $session_file = $this->session_dir . 'session_' . $account->id . '.madeline';
        
        if (!file_exists($session_file)) {
            \Log::warning("MTProto session path does not exist in setAccount", ['path' => $session_file]);
        } else {
            \Log::info("MTProto loading session in setAccount", ['path' => $session_file]);
        }
        
        $settings = $this->getSettings($account->api_id, $account->api_hash);
        $this->MadelineProto = new \danog\MadelineProto\API($session_file, $settings);
        return $this;
    }

    public function setSessionFile($session_file)
    {
        $this->includeMadeline();
        $settings = $this->getSettings();
        $this->MadelineProto = new \danog\MadelineProto\API($session_file, $settings);
        return $this;
    }

    public function login($phone, $api_id, $api_hash, $session_name = null)
    {
        $this->includeMadeline();
        \Log::info("MTProto Login Start (v8 Isolated)", ['phone' => $phone, 'api_id' => $api_id]);
        
        $session_file = $this->session_dir . ($session_name ?: 'session_temp_' . time()) . '.madeline';
        \Log::info("Session file path generated", ['path' => $session_file]);
        
        $settings = $this->getSettings($api_id, $api_hash);
        
        \Log::info("Isolated v8 Settings initialized (Layer 220 Support)");
        
        try {
            \Log::info("Initializing MadelineProto v8 Isolated API...");
            $this->MadelineProto = new \danog\MadelineProto\API($session_file, $settings);
            
            \Log::info("API initialized successfully, attempting phoneLogin...");
            $this->MadelineProto->phoneLogin($phone);
            \Log::info("phoneLogin called successfully (OTP should be sent)");
        } catch (\Exception $e) {
            \Log::error("MTProto Login Error (v8)", ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }
        
        return $session_file;
    }

    public function completeLogin($code)
    {
        $this->includeMadeline();
        // phoneLogin might return state, but session is persistent
        return $this->MadelineProto->completePhoneLogin($code);
    }

    public function complete2FA($password)
    {
        $this->includeMadeline();
        return $this->MadelineProto->complete2faLogin($password);
    }

    public function sendMessage($toId, $message)
    {
        $this->includeMadeline();
        
        // Distinguish between Peer IDs and Phone numbers.
        // Telegram internal Peer IDs are numeric.
        // Phone numbers should have + prepend if they don't already.
        // But we must NOT prepend + to an internal numeric Peer ID.
        // Peer IDs are usually large integers. Phone numbers including country code are also large.
        // We only prepend + if it's explicitly intended or if the ID doesn't look like a known Peer ID.
        // For now, if it's numeric AND starts with '+', leave it.
        // If it's numeric AND doesn't have '+', only add '+' if it's a likely phone number (> 11 digits starting with country code).
        if (is_numeric($toId) && strpos((string)$toId, '+') !== 0) {
            // If it's a username (starts with @), Madeline handles it.
            // If it's a raw numeric ID, Madeline handles it better WITHOUT +.
            // We ONLY add + if we are sure it's a phone number (not a Peer ID).
            // A simple heuristic: most numeric IDs from updates are Peer IDs.
            // If the length is exactly 12-13 and it looks like a phone, maybe add +.
            // BUT: safer to NOT add + if it's already a numeric string, as Madeline converts it to peer.
        }

        return $this->MadelineProto->messages->sendMessage([
            'peer' => $toId,
            'message' => $message,
        ]);
    }

    public function getMessages($limit = 20)
    {
        $this->includeMadeline();
        return $this->MadelineProto->getDialogs();
    }

    public function logout()
    {
        $this->includeMadeline();
        if ($this->MadelineProto) {
            $this->MadelineProto->logout();
        }
    }

    public function stop()
    {
        if ($this->MadelineProto) {
            unset($this->MadelineProto);
            $this->MadelineProto = null;
        }
    }

    public function startListener($account)
    {
        $this->includeMadeline();
        
        // Ensure EventHandler is loaded
        if (!class_exists('\App\Services\MtprotoEventHandler')) {
            require_once __DIR__ . '/MtprotoEventHandler.php';
        }

        // Pass account context to the event handler via static properties
        \App\Services\MtprotoEventHandler::$user_id    = $account->user_id;
        \App\Services\MtprotoEventHandler::$account_id = $account->id;
        \App\Services\MtprotoEventHandler::$api_id     = $account->api_id;
        \App\Services\MtprotoEventHandler::$api_hash   = $account->api_hash;

        $session_file = $this->session_dir . 'session_' . $account->id . '.madeline';
        $settings = $this->getSettings($account->api_id, $account->api_hash);

        \Illuminate\Support\Facades\Log::info("Starting MTProto Live Listener for Account {$account->id}", ['session' => $session_file]);
        
        // MadelineProto v8: use the static EventHandler::startAndLoop pattern
        // This is the ONLY correct way to start the event handler in v8+
        \App\Services\MtprotoEventHandler::startAndLoop($session_file, $settings);
    }
}
