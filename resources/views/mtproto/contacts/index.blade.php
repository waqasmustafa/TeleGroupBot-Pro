@extends('layouts.auth')
@section('title', __('Contacts'))
@section('content')
<div class="main-content container-fluid">
    <div class="page-title pb-3">
        <h3>{{__('Contact Management')}}</h3>
    </div>

    <div class="row">
        @if(!$is_admin)
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h4>{{__('Import CSV Contacts')}}</h4></div>
                <div class="card-body">
                    <form action="{{route('mtproto.contacts.import')}}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group mb-3">
                            <label>{{__('List Name')}}</label>
                            <input type="text" name="list_name" class="form-control" required placeholder="e.g. Clients Category A">
                        </div>
                        <div class="form-group mb-3">
                            <label>{{__('Select CSV File')}}</label>
                            <input type="file" name="file" class="form-control" required>
                            <small class="text-muted">CSV Format: Username/Phone, Optional Phone, Optional Name</small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">{{__('Import & Save')}}</button>
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
                                <th>{{__('List Name')}}</th>
                                <th>{{__('Contacts Count')}}</th>
                                <th>{{__('Actions')}}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lists as $list)
                            <tr>
                                <td>{{$list->name}}</td>
                                <td>{{$list->contacts->count()}}</td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{route('mtproto.contacts.view', $list->id)}}" class="btn btn-sm btn-info">{{__('View')}}</a>
                                        @if(!$is_admin)
                                        <form action="{{route('mtproto.contacts.delete', $list->id)}}" method="POST" onsubmit="return confirm('Are you sure you want to delete this list?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                        @endif
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
