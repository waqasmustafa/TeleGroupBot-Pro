@extends('layouts.auth')
@section('title', __('Verify OTP'))
@section('content')
<div class="main-content container-fluid">
    <div class="page-title pb-3">
        <h3>{{__('Verify Telegram OTP')}}</h3>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <form action="{{route('mtproto.verify.otp.submit')}}" method="POST">
                        @csrf
                        <div class="form-group mb-4">
                            <p>{{__('Enter the code sent to your Telegram app for')}} <b>{{session('mtproto_temp_phone')}}</b></p>
                            <input type="text" name="otp" class="form-control text-center" required placeholder="12345" style="font-size: 2rem; letter-spacing: 5px;">
                        </div>
                        <button type="submit" class="btn btn-success w-100">{{__('Complete Linking')}}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
