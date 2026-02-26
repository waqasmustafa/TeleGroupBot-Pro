@extends('layouts.auth')
@section('title', __('Marketing Campaigns'))
@section('content')
<div class="main-content container-fluid">
    <div class="page-title pb-3">
        <h3>{{__('Bulk DM Campaigns')}}</h3>
    </div>

    <div class="alert alert-info">
        DEBUG: Accounts: {{ $active_accounts->count() }} | 
        Templates: {{ $templates->count() }} | 
        Lists: {{ $lists->count() }} |
        UID: {{ Auth::id() }} |
        Type: {{ Auth::user()->user_type }}
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h4>{{__('Start New Campaign')}}</h4></div>
                <div class="card-body">
                    <form action="{{route('mtproto.campaigns.store')}}" method="POST">
                        @csrf
                        <div class="form-group mb-3">
                            <label>{{__('Campaign Name')}}</label>
                            <input type="text" name="campaign_name" class="form-control" required>
                        </div>
                        <div class="form-group mb-3">
                            <label>{{__('Target List')}}</label>
                            <select name="list_id" class="form-control" required>
                                <option value="">{{__('Select List')}}</option>
                                @foreach($lists as $list)
                                    <option value="{{$list->id}}">{{$list->name}} ({{$list->contacts->count()}})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label>{{__('Message Template(s)')}}</label>
                            <select name="template_ids[]" class="form-control select2-multi" multiple required>
                                @foreach($templates as $temp)
                                    <option value="{{$temp->id}}">{{$temp->title}}</option> {{-- Corrected to title --}}
                                @endforeach
                            </select>
                            <small class="text-muted">{{__('Select one or more. System will randomize them.')}}</small>
                        </div>
                        <div class="form-group mb-3">
                            <label>{{__('Sender Account(s)')}}</label>
                            <select name="account_ids[]" class="form-control select2-multi" multiple required>
                                @foreach($active_accounts as $acc)
                                    <option value="{{$acc->id}}">{{$acc->phone}}</option> {{-- Corrected to phone --}}
                                @endforeach
                            </select>
                            <small class="text-muted">{{__('System will rotate through selected accounts.')}}</small>
                        </div>
                        <div class="form-group mb-3">
                            <label>{{__('Time Interval (Minutes)')}}</label>
                            <input type="number" name="interval_min" class="form-control" value="5" min="1">
                            <small class="text-muted">{{__('Recommended: 1-5 mins to avoid bans.')}}</small>
                        </div>
                        <button type="submit" class="btn btn-success w-100">{{__('Launch Campaign')}}</button>
                    </form>
                </div>
            </div>
        </div>
        </div>
        <div class="col-md-{{$is_admin ? '12' : '8'}}">
            <div class="card">
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{__('Campaign')}}</th>
                                <th>{{__('Progress')}}</th>
                                <th>{{__('Status')}}</th>
                                <th>{{__('Actions')}}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($campaigns as $camp)
                            <tr id="campaign-row-{{$camp->id}}">
                                <td>{{$camp->campaign_name}}</td>
                                <td>
                                    @php $perc = $camp->total_recipients > 0 ? ($camp->sent_count / $camp->total_recipients) * 100 : 0; @endphp
                                    <div class="progress">
                                        <div class="progress-bar" id="progress-bar-{{$camp->id}}" role="progressbar" style="width: {{$perc}}%" aria-valuenow="{{$perc}}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small><span class="sent-count">{{$camp->sent_count}}</span> / <span class="total-count">{{$camp->total_recipients}}</span></small>
                                </td>
                                <td><span class="badge bg-secondary status-badge">{{strtoupper($camp->status)}}</span></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{route('mtproto.campaigns.logs', $camp->id)}}" class="btn btn-sm btn-info">{{__('Logs')}}</a>
                                        <form action="{{route('mtproto.campaigns.delete', $camp->id)}}" method="POST" onsubmit="return confirm('Are you sure you want to delete this campaign?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts-footer')
<script>
    $(document).ready(function() {
        // Initialize multi-select - DISABLED FOR DEBUG
        /*
        $('.select2-multi').select2({
            placeholder: "{{__('Select one or more')}}",
            allowClear: true,
            width: '100%'
        });
        */

        if (typeof global_mtproto_channel !== 'undefined' && global_mtproto_channel !== null) {
            global_mtproto_channel.bind('mtproto-realtime-event', function(data) {
                if (data.type == 'campaign') {
                    let camp = data.payload;
                    let $row = $('#campaign-row-' + camp.id);
                    
                    if ($row.length) {
                        // Update Progress Bar
                        let total = parseInt($row.find('.total-count').text()) || 0;
                        let perc = total > 0 ? (camp.sent_count / total) * 100 : 0;
                        $row.find('.progress-bar').css('width', perc + '%').attr('aria-valuenow', perc);
                        
                        // Update Counts
                        $row.find('.sent-count').text(camp.sent_count);
                        
                        // Update Status Badge
                        let $badge = $row.find('.status-badge');
                        $badge.text(camp.status.toUpperCase());
                        
                        // Update color based on status
                        if (camp.status == 'completed') $badge.removeClass('bg-secondary bg-warning').addClass('bg-success');
                        else if (camp.status == 'processing') $badge.removeClass('bg-secondary').addClass('bg-warning text-dark');
                    }
                }
            });
        }
    });
</script>
@endpush
