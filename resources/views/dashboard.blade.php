@extends('layouts.auth')
@section('title',__('Dashboard'))
@section('content')
<div class="main-content container-fluid">
    @auth
        {{-- Email verification disabled --}}
        {{-- @if (!auth()->user()->email_verified_at && !$is_manager)
            <div class="alert alert-light-warning alert-dismissible fade show p-4 border-warning border-dashed"  role="alert">
                <h5 class="alert-heading text-dark">
                    <i class="far fa-envelope-open fs-1 float-start mt-1 me-3"></i>
                    {{__('Verify Email')}} : <small>{{__('Email is not verified yet. Please verify your email.')}}</small>
                </h5>
                <p class="">{{ __('Click the link to get started') }} : <a href="{{ route('verification.notice') }}" class="text-success fw-bold">{{ __('Start Email Verification') }}</a></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif --}}
    @endauth

    <!-- FOR LOCALE FILE ENTRY PURPOSE -->
    <span class="d-none">
        {{__('Connect Bot')}}     
        {{__('Group Members')}}
        {{__('Group Management')}}
    </span>


    <div class="page-title pb-3">
        <h3 class="d-inline me-2">{{__('Dashboard')}}</h3>
        <div class="btn-group float-end">
            <button type="button" class="btn btn-sm btn-outline-light dropdown-toggle ms-2 rounded text-dark px-2 py-1 dashstyle1" data-bs-toggle="dropdown" aria-expanded="false">
                {{date('M', mktime(0, 0, 0, $dashboard_selected_month, 10))}}
            </button>
            <ul class="dropdown-menu onchange_action" id="change_month">
                <?php
                for($i=1;$i<=12;$i++){
                    $month_name = date('M', mktime(0, 0, 0, $i, 10));
                    $active = $dashboard_selected_month==$i ? '' : '';
                    echo '<li><a data-item="'.$i.'" class="dropdown-item '.$active.'" href="#">'.__($month_name).'</a></li>';
                }?>
            </ul>
            <button type="button" class="btn btn-sm btn-outline-light dropdown-toggle no-radius text-dark px-2 py-1" data-bs-toggle="dropdown" aria-expanded="false">
                {{$dashboard_selected_year}}
            </button>
            <ul class="dropdown-menu onchange_action" id="change_year">
                <?php
                for($i=date('Y');$i>(date('Y')-5);$i--){
                    $active = $dashboard_selected_year==$i ? '' : '';
                    echo '<li><a data-item="'.$i.'" class="dropdown-item '.$active.'" href="#">'.$i.'</a></li>';
                }?>
            </ul>
        </div>
    </div>



    <div class="clearfix"></div>
    <section class="section">

        <div class="row mt-2">
            <div class="col-12 col-md-4">
                <div class="card card-icon-bg-md box-shadow pb-0 dashstyle2">
                    <div class="card-body bg-light-purple ps-4 pe-2 dashstyle3">
                        <div class="row">
                            <div class="col">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-50px me-3">
                                        <div class="symbol-label bg-white">
                                            <i class="fas fa-user-circle text-primary fs-3"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="fs-4 text-dark fw-bold">{{$mtproto_account_count}}</div>
                                        <div class="fw-bold text-muted">{{__('Telegram Account')}}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card card-icon-bg-md box-shadow pb-0 dashstyle4">
                    <div class="card-body bg-white ps-4 pe-2 dashstyle5">
                        <div class="row">
                            <div class="col">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-50px me-3">
                                        <div class="symbol-label bg-primary">
                                            <i class="fas fa-address-book text-white fs-3"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="fs-4 text-dark fw-bold">{{$mtproto_contact_count}}</div>
                                        <div class="fw-bold text-muted">{{__('Contacts')}}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card card-icon-bg-md box-shadow pb-0 dashstyle6">
                    <div class="card-body bg-light-purple ps-4 pe-2 dashstyle5">
                        <div class="row">
                            <div class="col">
                                <div class="d-flex align-items-center">
                                    <div class="symbol symbol-50px me-3">
                                        <div class="symbol-label bg-white">
                                            <i class="fas fa-file-alt text-primary fs-3"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="fs-4 text-dark fw-bold">{{$mtproto_template_count}}</div>
                                        <div class="fw-bold text-muted">{{__('Message Templates')}}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <div class="row">
            <div class="col-12 col-lg-4">
                <div class="card card-icon-bg-lg box-shadow">
                    <div class="card-header bg-primary bg-gradient p-0">
                        <h4 class="card-title text-white p-4">{{__('Message Campaign')}}</h4>
                        <canvas id="broadcast_summary" height="100px"></canvas>
                    </div>
                    <div class="card-body pb-0 px-4 px-md-3">
                        <div class="row g-0">
                            <div class="col bg-light-warning border-warning border-dashed ps-2 ps-md-3 pe-1 py-3 rounded-4 me-3 mb-3 ">
                                <span class="text-warning d-block my-2">
                                     <i class="fas fa-paper-plane fs-6"></i> <span class="fs-6 ms-0 fw-bold">{{$pending_campaign}}</span>
                                </span>
                                <a href="#" class="text-sm text-muted">{{__('Pending')}}</a>
                            </div>
                            <div class="col bg-light-success border-success border-dashed ps-2 ps-md-3 pe-1 py-3 rounded-4 me-3 mb-3">
                                <span class="text-success d-block my-2">
                                     <i class="fas fa-paper-plane fs-6"></i> <span class="fs-6 ms-0 fw-bold">{{$completed_campaign}}</span>
                                </span>
                                <a href="#" class="text-sm text-muted">{{__('Completed')}}</a>
                            </div>
                            <div class="col bg-light-danger border-danger border-dashed ps-2 ps-md-3 pe-1 py-3 rounded-4 mb-3">
                                <span class="text-danger d-block my-2">
                                     <i class="fas fa-paper-plane fs-6"></i> <span class="fs-6 ms-0 fw-bold">{{$processing_campaign}}</span>
                                </span>
                                <a href="#" class="text-sm text-muted">{{__('Processing')}}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </section>
</div>
@endsection

@push('scripts-footer')
<script>
    "use strict";
    var broadcast_summary_data = [];
    var broadcast_summary_days_data = [];
    var pending_campaign = '{{__('Pending Campaign')}}';
    var message_pending = '{{__('Message Pending')}}';
    var message_sent = '{{__('Message Sent')}}';
    var dashboard_change_data = "{{route('dashboard-change-data')}}";
</script>
<script src="{{ asset('assets/vendors/chartjs/Chart.min.js') }}"></script>
<script src="{{ asset('assets/vendors/apexcharts/apexcharts.min.js') }}"></script>
<script src="{{ asset('assets/js/pages/dashboard.js') }}"></script>
@endpush

@push('styles-header')
<link rel="stylesheet" href="{{ asset('assets/vendors/chartjs/Chart.min.css') }}">
<link  rel="stylesheet" href="{{ asset('assets/css/dashboard.css')}}"></script>
@endpush
