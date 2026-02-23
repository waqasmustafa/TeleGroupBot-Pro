@extends('layouts.auth')
@section('title', __('Campaign Logs'))
@section('content')
<div class="main-content container-fluid">
    <div class="page-title pb-3">
        <div class="row">
            <div class="col-12 col-md-6">
                <a href="{{route('mtproto.campaigns.index')}}" class="btn btn-sm btn-secondary mb-2"><i class="fas fa-arrow-left"></i> {{__('Back to Campaigns')}}</a>
                <h3>{{__('Logs for')}}: {{$campaign->campaign_name}}</h3>
                <p class="text-subtitle text-muted">{{__('Detailed delivery report for each contact.')}}</p>
            </div>
            <div class="col-12 col-md-6 text-end">
                <div class="badge bg-success p-2">{{__('Sent')}}: {{$campaign->sent_count}}</div>
                <div class="badge bg-danger p-2">{{__('Failed')}}: {{$campaign->failed_count}}</div>
                <div class="badge bg-primary p-2">{{__('Total')}}: {{$campaign->total_recipients}}</div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{__('Contact')}}</th>
                                <th>{{__('Status')}}</th>
                                <th>{{__('Sent Via (IP)')}}</th>
                                <th>{{__('Error/Info')}}</th>
                                <th>{{__('Time')}}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                            <tr>
                                <td>{{$log->contact_identifier}}</td>
                                <td>
                                    @if($log->status == 'success')
                                        <span class="badge bg-success">{{strtoupper(__('Delivered'))}}</span>
                                    @else
                                        <span class="badge bg-danger">{{strtoupper(__('Failed'))}}</span>
                                    @endif
                                </td>
                                <td>
                                    <small class="badge bg-light text-dark">{{$log->sent_via ?? 'N/A'}}</small>
                                </td>
                                <td>
                                    @if($log->status == 'failed')
                                        <small class="text-danger">{{$log->error}}</small>
                                    @else
                                        <small class="text-muted">{{__('Message sent successfully')}}</small>
                                    @endif
                                </td>
                                <td>{{$log->message_time}}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">{{__('No logs found for this campaign.')}}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
