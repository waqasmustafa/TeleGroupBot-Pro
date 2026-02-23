@extends('layouts.auth')
@section('title', __('Telegram Account'))
@section('content')
<div class="main-content container-fluid">
    <div class="page-title pb-3">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h3>{{__('Telegram Account')}}</h3>
                <p class="text-subtitle text-muted">{{__('Manage your Telegram user accounts for bulk DMing.')}}</p>
            </div>
            @if(!$is_admin)
            <div class="col-12 col-md-6 order-md-2 order-first text-end">
                <a href="{{route('mtproto.accounts.create')}}" class="btn btn-primary"><i class="fas fa-plus"></i> {{__('Link New Account')}}</a>
            </div>
            @endif
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{session('status')}}</div>
    @endif

    <section class="section">
        <div class="card">
            <div class="card-body">
                <table class="table table-striped" id="table1">
                    <thead>
                        <tr>
                            <th>{{__('Phone')}}</th>
                            <th>{{__('API ID')}}</th>
                            <th>{{__('Status')}}</th>
                            <th>{{__('Linked At')}}</th>
                            @if(!$is_admin)<th>{{__('Actions')}}@endif</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($accounts as $account)
                        <tr>
                            <td>{{$account->phone}}</td>
                            <td>{{$account->api_id}}</td>
                            <td>
                                @if($account->status == '1')
                                    <span class="badge bg-success">{{__('Active')}}</span>
                                @else
                                    <span class="badge bg-danger">{{__('Inactive')}}</span>
                                @endif
                            </td>
                            <td>{{$account->created_at}}</td>
                            @if(!$is_admin)
                            <td>
                                <form action="{{route('mtproto.accounts.delete', $account->id)}}" method="POST" onsubmit="return confirm('Are you sure you want to delete this account? This will also remove the session file.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
@endsection
