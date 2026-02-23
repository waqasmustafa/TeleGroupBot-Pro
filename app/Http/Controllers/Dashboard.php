<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Home;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Dashboard extends Home
{
    public function __construct()
    {
        $this->set_global_userdata();
    }

    public function index()
    {       
        if(session('email_just_verified')=='1'){
            $settings_data = DB::table('settings')->where('user_id',$this->parent_user_id)->first();
            $auto_responder_signup_settings = $settings_data->auto_responder_signup_settings ?? '';
            if(!empty($auto_responder_signup_settings)){
                $this->sync_email_to_autoresponder(
                    $auto_responder_signup_settings,
                    $email = Auth::user()->email,
                    $first_name = Auth::user()->name,
                    $last_name = '',
                    $type='signup',
                    $this->parent_user_id
                );
            }
            session(['email_just_verified' => '0']);
        }

        if(!empty(request()->id)){
            $dashboard_user = request()->id;
            $check = DB::table('users')->where(['parent_user_id'=>$this->user_id,'id'=>$dashboard_user])->select('id')->first();
            $user_id = empty($check) ? $this->user_id : $dashboard_user;
        }
        else $user_id = $this->user_id;

        $dashboard_selected_year = (int) session('dashboard_selected_year');
        if($dashboard_selected_year==0) $dashboard_selected_year = date('Y');
        $dashboard_selected_month = session('dashboard_selected_month');
        if($dashboard_selected_month=='') $dashboard_selected_month = date('m');
        $dashboard_selected_month_year = $dashboard_selected_year.'-'.$dashboard_selected_month;
        $previous_year = ($dashboard_selected_year-1);


        // MTProto STATS
        $mtproto_account_query = DB::table('mtproto_accounts');
        $mtproto_contact_query = DB::table('mtproto_contacts');
        $mtproto_template_query = DB::table('mtproto_templates');
        $mtproto_campaign_query = DB::table('mtproto_campaigns');

        if(!$this->is_admin || ($this->is_admin && !empty(request()->id))) {
            $mtproto_account_query->where('user_id', $user_id);
            $mtproto_contact_query->where('user_id', $user_id);
            $mtproto_template_query->where('user_id', $user_id);
            $mtproto_campaign_query->where('user_id', $user_id);
        }

        $mtproto_account_count = $mtproto_account_query->count();
        $mtproto_contact_count = $mtproto_contact_query->count();
        $mtproto_template_count = $mtproto_template_query->count();
        $mtproto_campaign_count = $mtproto_campaign_query->count();

        $completed_campaign_query = DB::table('mtproto_campaigns')->where('status', 'completed');
        $pending_campaign_query = DB::table('mtproto_campaigns')->where('status', 'pending');
        $processing_campaign_query = DB::table('mtproto_campaigns')->where('status', 'processing');

        if(!$this->is_admin || ($this->is_admin && !empty(request()->id))) {
            $completed_campaign_query->where('user_id', $user_id);
            $pending_campaign_query->where('user_id', $user_id);
            $processing_campaign_query->where('user_id', $user_id);
        }

        $completed_campaign = $completed_campaign_query->count();
        $pending_campaign = $pending_campaign_query->count();
        $processing_campaign = $processing_campaign_query->count();

        $data = [
            'mtproto_account_count' => $mtproto_account_count,
            'mtproto_contact_count' => $mtproto_contact_count,
            'mtproto_template_count' => $mtproto_template_count,
            'mtproto_campaign_count' => $mtproto_campaign_count,
            'completed_campaign' => $completed_campaign,
            'processing_campaign' => $processing_campaign,
            'pending_campaign' => $pending_campaign,
            'dashboard_selected_year' => $dashboard_selected_year,
            'dashboard_selected_month' => $dashboard_selected_month,
        ];
        $data['body'] = 'dashboard';
        return $this->viewcontroller($data);
    }


    public function dashboard_change_data(Request $request){
        $month = $request->month;
        $year = $request->year;
        $currency = $request->currency;
        if(!empty($month)) {
            $month = str_pad($month,2,'0',STR_PAD_LEFT);
            session(['dashboard_selected_month'=>$month]);
        }
        if(!empty($year)) session(['dashboard_selected_year'=>$year]);
        if(!empty($currency)) session(['dashboard_selected_currency'=>$currency]);
    }
}
