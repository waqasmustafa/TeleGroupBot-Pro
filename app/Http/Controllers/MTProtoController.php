<?php

namespace App\Http\Controllers;

use App\Models\MtprotoAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Services\MTProtoServiceInterface;
use App\Jobs\SendMTProtoCampaignJob;

class MTProtoController extends Home
{
    protected $mtproto;

    public function __construct(MTProtoServiceInterface $mtproto_service)
    {
        $this->set_global_userdata(true, ['Admin', 'Agent', 'Member']);
        $this->mtproto = $mtproto_service;
    }

    public function index()
    {
        $query = MtprotoAccount::query();
        if(!$this->is_admin) $query->where('user_id', $this->user_id);
        
        $accounts = $query->get();
        $data = [
            'body' => 'mtproto.accounts.index',
            'accounts' => $accounts
        ];
        return $this->viewcontroller($data);
    }

    public function addAccount()
    {
        $data = ['body' => 'mtproto.accounts.create'];
        return $this->viewcontroller($data);
    }

    public function storeAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'api_id' => 'required|integer',
            'api_hash' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        try {
            // Create or update account record first to get ID for session
            $account = MtprotoAccount::updateOrCreate(
                ['phone' => $request->phone, 'user_id' => $this->user_id],
                [
                    'api_id' => $request->api_id,
                    'api_hash' => $request->api_hash,
                    'proxy_host' => $request->proxy_host,
                    'proxy_port' => $request->proxy_port,
                    'proxy_user' => $request->proxy_user,
                    'proxy_pass' => $request->proxy_pass,
                    'status' => '0' // Inactive until OTP verified
                ]
            );

            $session_name = 'session_' . $account->id;
            $proxy = [
                'host' => $request->proxy_host,
                'port' => $request->proxy_port,
                'user' => $request->proxy_user,
                'pass' => $request->proxy_pass
            ];

            $session_file = $this->mtproto->login(
                $request->phone,
                $request->api_id,
                $request->api_hash,
                $session_name,
                $proxy
            );

            // Store info in session for next step
            session([
                'mtproto_temp_phone' => $request->phone,
                'mtproto_temp_api_id' => $request->api_id,
                'mtproto_temp_api_hash' => $request->api_hash,
                'mtproto_temp_session' => $session_file,
                'mtproto_account_id' => $account->id
            ]);

            return redirect()->route('mtproto.verify.otp')->with('status', __('OTP sent to your Telegram. Please enter it below.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function showVerifyOtp()
    {
        if (!session('mtproto_temp_phone')) {
            return redirect()->route('mtproto.accounts.create');
        }
        $data = ['body' => 'mtproto.accounts.verify-otp'];
        return $this->viewcontroller($data);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate(['otp' => 'required']);

        try {
            $this->mtproto->setSessionFile(session('mtproto_temp_session'))->completeLogin($request->otp);
            
            // Success! Stop the service to release file locks
            $this->mtproto->stop();

            // Find the account and activate it
            $account = MtprotoAccount::findOrFail(session('mtproto_account_id'));
            $account->update([
                'status' => '1',
                'session_path' => session('mtproto_temp_session')
            ]);

            session()->forget(['mtproto_temp_phone', 'mtproto_temp_api_id', 'mtproto_temp_api_hash', 'mtproto_temp_session', 'mtproto_account_id']);

            return redirect()->route('mtproto.accounts.index')->with('status', __('Account linked successfully!'));
        } catch (\Exception $e) {
            // Handle 2FA if needed
            if (strpos($e->getMessage(), '2FA') !== false) {
                return redirect()->route('mtproto.verify.2fa');
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function deleteAccount($id)
    {
        $account = MtprotoAccount::when(!$this->is_admin, function($q) {
            return $q->where('user_id', $this->user_id);
        })->findOrFail($id);
        
        // Remove session file/directory using Laravel File facade
        $session_path = storage_path('app/mtproto/session_' . $account->id . '.madeline');
        if (file_exists($session_path)) {
            \Illuminate\Support\Facades\File::deleteDirectory($session_path);
            // Fallback for single file sessions
            if (file_exists($session_path)) {
                @unlink($session_path);
            }
        }

        $account->delete();

        return redirect()->back()->with('status', __('Account deleted successfully!'));
    }

    // Contacts
    public function contactsIndex()
    {
        $query = \App\Models\MtprotoContactList::query();
        if(!$this->is_admin) $query->where('user_id', $this->user_id);

        $lists = $query->get();
        $data = [
            'body' => 'mtproto.contacts.index',
            'lists' => $lists
        ];
        return $this->viewcontroller($data);
    }

    public function importContacts(Request $request)
    {
        $request->validate([
            'list_name' => 'required',
            'file' => 'required|mimes:csv,txt'
        ]);

        $list = \App\Models\MtprotoContactList::create([
            'user_id' => $this->user_id,
            'name' => $request->list_name
        ]);

        $file = $request->file('file');
        if (($handle = fopen($file->getRealPath(), "r")) !== FALSE) {
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (empty(array_filter($row))) continue;

                $username = isset($row[0]) ? trim($row[0]) : null;
                $phone = isset($row[1]) ? trim($row[1]) : null;
                $first_name = isset($row[2]) ? trim($row[2]) : null;

                // Safety: Convert scientific notation back to string if detected
                if (stripos((string)$username, 'E+') !== false) $username = number_format((float)$username, 0, '', '');
                if (stripos((string)$phone, 'E+') !== false) $phone = number_format((float)$phone, 0, '', '');

                \App\Models\MtprotoContact::create([
                    'user_id' => $this->user_id,
                    'list_id' => $list->id,
                    'username' => $username,
                    'phone' => $phone,
                    'first_name' => $first_name,
                ]);
            }
            fclose($handle);
        }

        return redirect()->back()->with('status', __('Contacts imported successfully!'));
    }

    public function viewContactList($id)
    {
        $list = \App\Models\MtprotoContactList::when(!$this->is_admin, function($q) {
            return $q->where('user_id', $this->user_id);
        })->with('contacts')->findOrFail($id);
        $data = [
            'body' => 'mtproto.contacts.view',
            'list' => $list
        ];
        return $this->viewcontroller($data);
    }

    public function deleteContactList($id)
    {
        $list = \App\Models\MtprotoContactList::when(!$this->is_admin, function($q) {
            return $q->where('user_id', $this->user_id);
        })->findOrFail($id);
        // Cascading delete might be handled by DB or manually
        \App\Models\MtprotoContact::where('list_id', $list->id)->delete();
        $list->delete();

        return redirect()->back()->with('status', __('Contact list deleted successfully!'));
    }

    // Templates
    public function templatesIndex()
    {
        $query = \App\Models\MtprotoTemplate::query();
        if(!$this->is_admin) $query->where('user_id', $this->user_id);

        $templates = $query->get();
        $data = [
            'body' => 'mtproto.templates.index',
            'templates' => $templates
        ];
        return $this->viewcontroller($data);
    }

    public function storeTemplate(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'message' => 'required'
        ]);

        \App\Models\MtprotoTemplate::create([
            'user_id' => $this->user_id,
            'title' => $request->title,
            'message' => $request->message
        ]);

        return redirect()->back()->with('status', __('Template saved!'));
    }

    public function deleteTemplate($id)
    {
        $template = \App\Models\MtprotoTemplate::when(!$this->is_admin, function($q) {
            return $q->where('user_id', $this->user_id);
        })->findOrFail($id);
        $template->delete();

        return redirect()->back()->with('status', __('Template deleted successfully!'));
    }

    // Campaigns
public function campaignsIndex()
{
    $is_admin = Auth::user()->user_type == 'Admin';
    $user_id = Auth::id();

    $query = \App\Models\MtprotoCampaign::with(['list', 'template']);
    if(!$is_admin) $query->where('user_id', $user_id);
    $campaigns = $query->latest()->get();

    // Lists
    $list_query = \App\Models\MtprotoContactList::query();
    if(!$is_admin) $list_query->where('user_id', $user_id);
    $lists = $list_query->get();

    // Templates
    $template_query = \App\Models\MtprotoTemplate::query();
    if(!$is_admin) $template_query->where('user_id', $user_id);
    $templates = $template_query->get();

    // Accounts
    $account_query = \App\Models\MtprotoAccount::where('status', '1');
    if(!$is_admin) $account_query->where('user_id', $user_id);
    $active_accounts = $account_query->get();

    $data = [
        'body' => 'mtproto.campaigns.index',
        'campaigns' => $campaigns,
        'lists' => $lists,
        'templates' => $templates,
        'active_accounts' => $active_accounts
    ];
    return $this->viewcontroller($data);
}

    public function campaignLogs($id)
    {
        $campaign = \App\Models\MtprotoCampaign::when(!$this->is_admin, function($q) {
            return $q->where('user_id', $this->user_id);
        })->findOrFail($id);
        $logs = \App\Models\MtprotoMessage::where('campaign_id', $campaign->id)->latest()->get();

        $data = [
            'body' => 'mtproto.campaigns.logs',
            'campaign' => $campaign,
            'logs' => $logs
        ];
        return $this->viewcontroller($data);
    }

    public function storeCampaign(Request $request)
    {
        $request->validate([
            'campaign_name' => 'required',
            'list_id'       => 'required',
            'template_ids'  => 'required|array',
            'account_ids'   => 'required|array',
            'interval_min'  => 'required|integer|min:1'
        ]);

        $list = \App\Models\MtprotoContactList::findOrFail($request->list_id);

        $campaign = \App\Models\MtprotoCampaign::create([
            'user_id'          => Auth::id(),
            'account_ids'      => $request->account_ids,
            'template_ids'     => $request->template_ids,
            'account_id'       => $request->account_ids[0] ?? null, // Fallback for single-account logic
            'template_id'      => $request->template_ids[0] ?? null, // Fallback for single-template logic
            'list_id'          => $request->list_id,
            'campaign_name'    => $request->campaign_name,
            'interval_min'     => $request->interval_min,
            'status'           => 'pending',
            'total_recipients' => $list->contacts()->count()
        ]);

        // Dispatch the job
        SendMTProtoCampaignJob::dispatch($campaign->id);

        return redirect()->back()->with('status', __('Campaign created and dispatched!'));
    }

    public function deleteCampaign($id)
    {
        $campaign = \App\Models\MtprotoCampaign::when(!$this->is_admin, function($q) {
            return $q->where('user_id', $this->user_id);
        })->findOrFail($id);
        $campaign->delete();

        return redirect()->back()->with('status', __('Campaign deleted successfully!'));
    }

    // Inbox
    public function inbox()
    {
        $query = \App\Models\MtprotoMessage::query();
        if(!$this->is_admin) $query->where('user_id', $this->user_id);

        $conversations = $query->select('contact_identifier', 'account_id', DB::raw('MAX(message_time) as last_msg'))
            ->groupBy('contact_identifier', 'account_id')
            ->orderBy('last_msg', 'desc')
            ->get();

        $active_accounts = \App\Models\MtprotoAccount::where('status', '1')->when(!$this->is_admin, function($q) {
            return $q->where('user_id', $this->user_id);
        })->get();

        $data = [
            'body' => 'mtproto.inbox.index',
            'conversations' => $conversations,
            'active_accounts' => $active_accounts
        ];
        return $this->viewcontroller($data);
    }

    public function getChatMessages($identifier)
    {
        $query = \App\Models\MtprotoMessage::where('contact_identifier', $identifier);
        if(!$this->is_admin) $query->where('user_id', $this->user_id);
        
        $messages = $query->orderBy('message_time', 'asc')->get();

        return response()->json($messages);
    }

    public function sendReply(Request $request)
    {
        $request->validate([
            'identifier' => 'required',
            'message' => 'required'
        ]);

        $account_query = \App\Models\MtprotoAccount::where('status', '1');
        if(!$this->is_admin) $account_query->where('user_id', $this->user_id);
        
        // If a specific account_id is passed, try to use that
        if($request->has('account_id')) {
            $account = (clone $account_query)->where('id', $request->account_id)->first();
        } else {
            $account = $account_query->first();
        }

        if (!$account) return response()->json(['error' => 'No active account found for reply'], 400);

        // Save message immediately to DB with 'pending' status
        $msg = \App\Models\MtprotoMessage::create([
            'user_id'            => $account->user_id,
            'account_id'         => $account->id,
            'contact_identifier' => $request->identifier,
            'direction'          => 'out',
            'message'            => $request->message,
            'status'             => 'pending', 
            'sent_via'           => $account->proxy_host ? $account->proxy_host : 'Local Server IP',
            'message_time'       => now()
        ]);

        // Dispatch to queue worker — this avoids the proc_open/IPC restriction on Windows/XAMPP web server
        \App\Jobs\SendInboxReplyJob::dispatch(
            $account->id,
            $account->user_id,
            $request->identifier,
            $request->message,
            $msg->id
        );

        \Log::info("Inbox reply queued", ['to' => $request->identifier, 'account' => $account->id, 'msg_id' => $msg->id]);

        return response()->json(['success' => true, 'message_obj' => $msg]);
    }
}
