@extends('layouts.auth')
@section('title', __('Message Templates'))
@section('content')
<div class="main-content container-fluid">
    <div class="page-title pb-3">
        <div class="row">
            <div class="col-12 col-md-6">
                <h3>{{__('Message Templates')}}</h3>
                <p class="text-subtitle text-muted">{{__('Create reusable templates with placeholders like {first_name}.')}}</p>
            </div>
        </div>
    </div>

    <div class="row">
        @if(!$is_admin)
        <div class="col-md-4">
            <div class="card">
                <div class="card-header"><h4>{{__('Add New Template')}}</h4></div>
                <div class="card-body">
                    <form action="{{route('mtproto.templates.store')}}" method="POST">
                        @csrf
                        <div class="form-group mb-3">
                            <label>{{__('Template Name')}}</label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Intro Message">
                        </div>
                        <div class="form-group mb-3">
                            <label>{{__('Message Content')}}</label>
                            <textarea name="message" class="form-control" rows="5" required placeholder="Hello {first_name}, how are you?"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">{{__('Save Template')}}</button>
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
                                <th>{{__('Title')}}</th>
                                <th>{{__('Message')}}</th>
                                @if(!$is_admin)<th>{{__('Actions')}}@endif</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($templates as $template)
                            <tr>
                                <td>{{$template->title}}</td>
                                <td>{{Str::limit($template->message, 50)}}</td>
                                @if(!$is_admin)
                                <td>
                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#viewModal{{$template->id}}"><i class="fas fa-eye"></i></button>
                                    <form action="{{route('mtproto.templates.delete', $template->id)}}" method="POST" class="d-inline" onsubmit="return confirm('{{__('Are you sure?')}}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                                @endif
                            </tr>

                            <!-- View Modal -->
                            <div class="modal fade" id="viewModal{{$template->id}}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">{{$template->title}}</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p style="white-space: pre-wrap;">{{$template->message}}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
