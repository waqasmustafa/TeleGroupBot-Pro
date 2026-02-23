@extends('layouts.auth')
@section('title', __('Link Telegram Account'))
@section('content')
<div class="main-content container-fluid">
    <div class="page-title pb-3">
        <h3>{{__('Link New Telegram Account')}}</h3>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <form action="{{route('mtproto.accounts.store')}}" method="POST">
                        @csrf
                        <div class="form-group mb-3">
                            <label>{{__('Phone Number')}} (with country code, e.g. +1...)</label>
                            <input type="text" name="phone" class="form-control" required placeholder="+123456789">
                        </div>
                        <div class="form-group mb-3">
                            <label>{{__('API ID')}}</label>
                            <input type="text" name="api_id" class="form-control" required placeholder="Get from my.telegram.org">
                        </div>
                        <div class="form-group mb-3">
                            <label>{{__('API Hash')}}</label>
                            <input type="text" name="api_hash" class="form-control" required placeholder="Get from my.telegram.org">
                        </div>

                        <hr>
                        <h5>{{__('Proxy Settings (Optional)')}}</h5>
                        <p class="text-muted small">{{__('Recommended to avoid IP bans if linking multiple accounts.')}}</p>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group mb-3">
                                    <label>{{__('Proxy Host (SOCKS5)')}}</label>
                                    <input type="text" name="proxy_host" class="form-control" placeholder="e.g. 1.2.3.4">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-3">
                                    <label>{{__('Proxy Port')}}</label>
                                    <input type="number" name="proxy_port" class="form-control" placeholder="1080">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>{{__('Proxy Username')}}</label>
                                    <input type="text" name="proxy_user" class="form-control" placeholder="Optional">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>{{__('Proxy Password')}}</label>
                                    <input type="password" name="proxy_pass" class="form-control" placeholder="Optional">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">{{__('Send OTP')}}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
