@extends('layouts.auth')
@section('title', __('Marketing Campaigns'))
@section('content')
<div class="main-content container-fluid">
    <div class="page-title pb-3">
        <h3>{{__('Bulk DM Campaigns')}}</h3>
    </div>

    <div class="row">
        @if(!$is_admin)
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
                            <label>{{__('Message Template')}}</label>
                            <select name="template_id" class="form-control" required>
                                <option value="">{{__('Select Template')}}</option>
                                @foreach($templates as $temp)
                                    <option value="{{$temp->id}}">{{$temp->title}}</option>
                                @endforeach
                            </select>
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
        @endif
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
                            <tr>
                                <td>{{$camp->campaign_name}}</td>
                                <td>
                                    <div class="progress">
                                        @php $perc = $camp->total_recipients > 0 ? ($camp->sent_count / $camp->total_recipients) * 100 : 0; @endphp
                                        <div class="progress-bar" role="progressbar" style="width: {{$perc}}%" aria-valuenow="{{$perc}}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small>{{$camp->sent_count}} / {{$camp->total_recipients}}</small>
                                </td>
                                <td><span class="badge bg-secondary">{{strtoupper($camp->status)}}</span></td>
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
