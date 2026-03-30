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

    private function getSettings($api_id = null, $api_hash = null, $proxy = null)
    {
        $settings = new \danog\MadelineProto\Settings();
        
        // Suppress browser output
        $settings->getLogger()->setType(\danog\MadelineProto\Logger::FILE_LOGGER);
        $settings->getLogger()->setExtra(storage_path('logs/madeline.log'));
        
        // Connection settings
        $connectionSettings = new \danog\MadelineProto\Settings\Connection();
        $connectionSettings->setIpv6(false); 
        $connectionSettings->setUseDoH(true); 
        $connectionSettings->setObfuscated(true);
        $connectionSettings->setProtocol(\danog\MadelineProto\Stream\MTProtoTransport\IntermediatePaddedStream::class);

        // Apply Proxy if available
        if ($proxy && !empty($proxy['host'])) {
            $connectionSettings->addProxy(\danog\MadelineProto\Stream\Proxy\SocksProxy::class, [
                'address'  => $proxy['host'],
                'port'     => (int)$proxy['port'],
                'username' => $proxy['user'] ?? null,
                'password' => $proxy['pass'] ?? null,
            ]);
        }

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
        
        $proxy = [
            'host' => $account->proxy_host,
            'port' => $account->proxy_port,
            'user' => $account->proxy_user,
            'pass' => $account->proxy_pass
        ];

        $settings = $this->getSettings($account->api_id, $account->api_hash, $proxy);
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

    public function login($phone, $api_id, $api_hash, $session_name = null, $proxy = null)
    {
        $this->includeMadeline();
        \Log::info("MTProto Login Start (v8 Isolated)", ['phone' => $phone, 'api_id' => $api_id]);
        
        $session_file = $this->session_dir . ($session_name ?: 'session_temp_' . time()) . '.madeline';
        \Log::info("Session file path generated", ['path' => $session_file]);
        
        $settings = $this->getSettings($api_id, $api_hash, $proxy);
        
        \Log::info("Isolated v8 Settings initialized (Layer 220 Support) with Proxy: " . ($proxy['host'] ?? 'None'));
        
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

    /**
     * Resolve a phone number peer by importing it into Telegram contacts first.
     * This is required because MadelineProto cannot send to a phone number
     * that is not in its internal peer database.
     */
    private function resolvePhonePeer(string $phone): string
    {
        // Clean the phone: ensure it has +
        if (strpos($phone, '+') !== 0) {
            $phone = '+' . $phone;
        }

        try {
            $result = $this->MadelineProto->contacts->importContacts([
                'contacts' => [[
                    '_'          => 'inputPhoneContact',
                    'client_id'  => rand(100000, 999999),
                    'phone'      => $phone,
                    'first_name' => 'Contact',
                    'last_name'  => '',
                ]]
            ]);

            // If we got a valid user back, use their ID
            if (!empty($result['users'][0]['id'])) {
                return (string)$result['users'][0]['id'];
            }
        } catch (\Exception $e) {
            \Log::warning("resolvePhonePeer failed for {$phone}: " . $e->getMessage());
        }

        // Fallback: return the phone number as-is
        return $phone;
    }

    public function sendMessage($toId, $message)
    {
        $this->includeMadeline();

        // If it looks like a phone number, resolve it via importContacts
        if (is_numeric(ltrim((string)$toId, '+')) && strlen(ltrim((string)$toId, '+')) > 8) {
            $toId = $this->resolvePhonePeer((string)$toId);
        }

        return $this->MadelineProto->messages->sendMessage([
            'peer'    => $toId,
            'message' => $message,
        ]);
    }

    public function deleteMessages(array $messageIds, $revoke = true)
    {
        $this->includeMadeline();
        return $this->MadelineProto->messages->deleteMessages([
            'revoke' => $revoke,
            'id' => $messageIds,
        ]);
    }

    public function sendMedia($toId, $filePath, $message = '', $mediaType = 'document')
    {
        $this->includeMadeline();

        // If it looks like a phone number, resolve it via importContacts
        if (is_numeric(ltrim((string)$toId, '+')) && strlen(ltrim((string)$toId, '+')) > 8) {
            $toId = $this->resolvePhonePeer((string)$toId);
        }

        $media = [
            '_' => 'inputMediaUploadedDocument',
            'file' => $filePath,
            'attributes' => [['_' => 'documentAttributeFilename', 'file_name' => basename($filePath)]]
        ];

        if ($mediaType === 'photo') {
            $media = [
                '_' => 'inputMediaUploadedPhoto',
                'file' => $filePath
            ];
        } elseif ($mediaType === 'video') {
            // Send video as a document with video attribute
            $media = [
                '_' => 'inputMediaUploadedDocument',
                'file' => $filePath,
                'attributes' => [
                    ['_' => 'documentAttributeVideo', 'duration' => 0, 'w' => 0, 'h' => 0],
                    ['_' => 'documentAttributeFilename', 'file_name' => basename($filePath)]
                ]
            ];
        }

        return $this->MadelineProto->messages->sendMedia([
            'peer' => $toId,
            'media' => $media,
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
        $proxy = [
            'host' => $account->proxy_host,
            'port' => $account->proxy_port,
            'user' => $account->proxy_user,
            'pass' => $account->proxy_pass
        ];
        $settings = $this->getSettings($account->api_id, $account->api_hash, $proxy);

        \Illuminate\Support\Facades\Log::info("Starting MTProto Live Listener for Account {$account->id}", ['session' => $session_file]);
        
        // MadelineProto v8: use the static EventHandler::startAndLoop pattern
        // This is the ONLY correct way to start the event handler in v8+
        \App\Services\MtprotoEventHandler::startAndLoop($session_file, $settings);
    }
}
