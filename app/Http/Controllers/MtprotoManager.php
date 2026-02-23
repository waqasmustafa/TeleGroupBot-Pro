<?php

namespace App\Http\Controllers;

use App\Models\MtprotoAccount;
use App\Models\MtprotoContact;
use App\Models\MtprotoContactList;
use App\Models\MtprotoMessageTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MtprotoManager extends Home
{
    public function __construct()
    {
        $this->set_global_userdata(true, ['Admin', 'Agent', 'Member']);
    }

    /**
     * Account Management
     */
    public function account_manager()
    {
        $accounts = MtprotoAccount::where('user_id', $this->user_id)->get();
        $data = ['body' => 'mtproto/accounts/manager', 'accounts' => $accounts];
        return $this->viewcontroller($data);
    }

    /**
     * Contact List Management
     */
    public function contact_lists()
    {
        $lists = MtprotoContactList::where('user_id', $this->user_id)->withCount('contacts')->get();
        $data = ['body' => 'mtproto/contacts/lists', 'lists' => $lists];
        return $this->viewcontroller($data);
    }

    public function upload_contacts_csv(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'list_name' => 'required|string|max:255',
            'csv_file' => 'required|file|mimes:csv,txt'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        try {
            DB::beginTransaction();

            // Create list
            $list = MtprotoContactList::create([
                'user_id' => $this->user_id,
                'list_name' => $request->list_name
            ]);

            $path = $request->file('csv_file')->getRealPath();
            $data = array_map('str_getcsv', file($path));
            $header = array_shift($data);

            $contacts = [];
            foreach ($data as $row) {
                if (count($row) < 1) continue;
                
                // Assuming columns: username, phone_number, first_name, last_name
                // Basic mapping based on existence
                $contact = [
                    'list_id' => $list->id,
                    'username' => $row[0] ?? null,
                    'phone_number' => $row[1] ?? null,
                    'first_name' => $row[2] ?? null,
                    'last_name' => $row[3] ?? null,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $contacts[] = $contact;
            }

            // Chunk insert for performance
            foreach (array_chunk($contacts, 100) as $chunk) {
                MtprotoContact::insert($chunk);
            }

            DB::commit();
            return response()->json(['error' => false, 'message' => __('Contacts uploaded successfully.')]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Template Management
     */
    public function message_templates()
    {
        $templates = MtprotoMessageTemplate::where('user_id', $this->user_id)->get();
        $data = ['body' => 'mtproto/templates/manager', 'templates' => $templates];
        return $this->viewcontroller($data);
    }

    public function save_template(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'template_name' => 'required|string|max:255',
            'message' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()]);
        }

        MtprotoMessageTemplate::updateOrCreate(
            ['id' => $request->id, 'user_id' => $this->user_id],
            ['template_name' => $request->template_name, 'message' => $request->message]
        );

        return response()->json(['error' => false, 'message' => __('Template saved successfully.')]);
    }
}
