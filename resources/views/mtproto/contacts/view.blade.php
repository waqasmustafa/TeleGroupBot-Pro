@extends('layouts.auth')
@section('title', __('View Contacts'))
@section('content')
<div class="main-content container-fluid">
    <div class="page-title pb-3">
        <h3>{{__('Contacts in')}}: {{$list->name}}</h3>
        <a href="{{route('mtproto.contacts.index')}}" class="btn btn-sm btn-secondary">{{__('Back to Lists')}}</a>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{__('Username/Identifier')}}</th>
                                <th>{{__('Phone')}}</th>
                                <th>{{__('Name')}}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($list->contacts as $contact)
                            <tr>
                                <td>{{$contact->username}}</td>
                                <td>{{$contact->phone}}</td>
                                <td>{{$contact->first_name}}</td>
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
