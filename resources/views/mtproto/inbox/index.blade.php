@extends('layouts.auth')
@section('title', __('Unified Inbox'))
@section('content')
<div class="main-content container-fluid">
    <div class="page-title pb-3">
        <h3>{{__('Telegram CRM Inbox')}}</h3>
    </div>

    <div class="row" style="height: 75vh;">
        <!-- Conversation List -->
        <div class="col-md-4 h-100 d-flex flex-column">
            <!-- Account Tabs -->
            <ul class="nav nav-tabs mb-2" id="accountTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active py-2 px-3" id="all-tab" data-bs-toggle="tab" href="#all" role="tab" data-account-id="all">{{__('All')}}</a>
                </li>
                @foreach($active_accounts as $acc)
                    <li class="nav-item">
                        <a class="nav-link py-2 px-3" id="acc-tab-{{$acc->id}}" data-bs-toggle="tab" href="#acc-{{$acc->id}}" role="tab" data-account-id="{{$acc->id}}">{{ substr($acc->phone, -4) }}</a>
                    </li>
                @endforeach
            </ul>

            <div class="card flex-grow-1 overflow-auto">
                <div class="card-header py-2"><h4>{{__('Chats')}}</h4></div>
                <div class="list-group list-group-flush" id="conversation-list">
                    @foreach($conversations as $chat)
                        <a href="#" class="list-group-item list-group-item-action contact-item" data-id="{{$chat->contact_identifier}}" data-account-id="{{$chat->account_id}}">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">{{$chat->contact_identifier}}</h6>
                                <small class="text-muted">{{$chat->last_msg}}</small>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Chat Window -->
        <div class="col-md-8 h-100">
            <div class="card h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 id="chat-title" class="mb-0">{{__('Select a chat')}}</h5>
                    <span id="active-account-badge" class="badge bg-info d-none"></span>
                </div>
                <div class="card-body overflow-auto d-flex flex-column" id="chat-messages" style="background: #f0f2f5;">
                    <!-- Messages will appear here -->
                </div>
                <div class="card-footer">
                    <form id="reply-form" class="d-none">
                        <input type="hidden" id="active-account-id" value="">
                        <div class="input-group">
                            <input type="text" id="message-input" class="form-control" placeholder="{{__('Type your reply...')}}">
                            <button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts-footer')
<script>
    "use strict";
    let activeContact = null;
    let activeAccount = null;
    const baseUrl = "{{ url('/') }}";

    // Tab Filtering
    $('#accountTabs a').on('click', function (e) {
        let accountId = $(this).data('account-id');
        if (accountId === 'all') {
            $('.contact-item').show();
        } else {
            $('.contact-item').hide();
            $(`.contact-item[data-account-id="${accountId}"]`).show();
        }
    });

    $(document).on('click', '.contact-item', function(e) {
        e.preventDefault();
        $('.contact-item').removeClass('active bg-light');
        $(this).addClass('active bg-light');
        
        activeContact = $(this).data('id').toString();
        activeAccount = $(this).data('account-id');
        
        $('#active-account-id').val(activeAccount);
        $('#chat-title').text('Chat with ' + activeContact);
        $('#active-account-badge').text('Account ID: ' + activeAccount).removeClass('d-none');
        
        $('#reply-form').removeClass('d-none');
        loadMessages(activeContact);
    });

    function scrollToBottom() {
        let el = document.getElementById('chat-messages');
        if (el) {
            setTimeout(function() { el.scrollTop = el.scrollHeight; }, 50);
        }
    }

    function loadMessages(identifier) {
        if(!identifier) return;
        
        $('#chat-messages').html('<div class="text-center my-auto"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i><br>Loading messages...</div>');

        $.ajax({
            url: baseUrl + "/mtproto/inbox/messages/" + encodeURIComponent(identifier),
            method: 'GET',
            success: function(messages) {
                let html = '';
                if(!messages || messages.length === 0) {
                    html = '<div class="text-center my-auto text-muted">No messages found.</div>';
                } else {
                    messages.forEach(function(msg) {
                        let align = msg.direction === 'out' ? 'align-self-end bg-primary text-white' : 'align-self-start bg-white';
                        let timeLabel = msg.message_time ? msg.message_time.substring(0, 16) : '';
                        html += `<div class="p-2 mb-2 rounded shadow-sm ${align}" style="max-width: 70%;">
                                    <div style="white-space: pre-wrap;">${msg.message}</div>
                                    <div class="text-end small ${msg.direction === 'out' ? 'text-white-50' : 'text-muted'}" style="font-size:0.7rem;">${timeLabel}</div>
                                 </div>`;
                    });
                }
                $('#chat-messages').html(html);
                scrollToBottom();
            },
            error: function(xhr) {
                console.error("Failed to load messages:", xhr);
                $('#chat-messages').html('<div class="text-center my-auto text-danger">Failed to load messages. Please try again.</div>');
            }
        });
    }

    $('#reply-form').on('submit', function(e) {
        e.preventDefault();
        let message = $('#message-input').val().trim();
        let accountId = $('#active-account-id').val();
        if(!message || !activeContact) return;

        let $btn = $(this).find('button[type="submit"]');
        let $input = $('#message-input');

        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $input.prop('disabled', true);

        $.ajax({
            url: "{{route('mtproto.inbox.send')}}",
            method: 'POST',
            data: {
                _token: "{{csrf_token()}}",
                identifier: activeContact,
                message: message,
                account_id: accountId
            },
            success: function(res) {
                $input.val('').prop('disabled', false).focus();
                $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i>');

                if(res.success && res.message_obj) {
                    let msg = res.message_obj;
                    let timeLabel = msg.message_time ? msg.message_time.substring(0, 16) : '';
                    let html = `<div class="p-2 mb-2 rounded shadow-sm align-self-end bg-primary text-white" style="max-width: 70%;">
                                    <div style="white-space: pre-wrap;">${msg.message}</div>
                                    <div class="text-end small text-white-50" style="font-size:0.7rem;">${timeLabel}</div>
                                </div>`;
                    $('#chat-messages .text-muted').remove();
                    $('#chat-messages').append(html);
                    scrollToBottom();
                } else if(!res.success) {
                    alert(res.error || "Failed to send message");
                }
            },
            error: function(xhr) {
                let errorMsg = xhr.responseJSON ? xhr.responseJSON.error : "Failed to send. Server error.";
                alert(errorMsg);
                $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i>');
                $input.prop('disabled', false);
            }
        });
    });

    $(document).ready(function() {
        // Auto-load first contact
        let $first = $('.contact-item').first();
        if ($first.length) { $first.trigger('click'); }

        // Check for URL parameters (from notifications)
        let urlParams = new URLSearchParams(window.location.search);
        let targetAccount = urlParams.get('account_id');
        let targetContact = urlParams.get('contact');
        
        if (targetAccount) {
            $(`#acc-tab-${targetAccount}`).trigger('click');
            if (targetContact) {
                $(`.contact-item[data-id="${targetContact}"][data-account-id="${targetAccount}"]`).trigger('click');
            }
        }

        // Real-time Inbox Updates
        if (typeof global_mtproto_channel !== 'undefined' && global_mtproto_channel !== null) {
            global_mtproto_channel.bind('mtproto-realtime-event', function(data) {
                if (data.type == 'message') {
                    let msg = data.payload.message;
                    let identifier = data.payload.identifier;
                    let accountId = msg.account_id;

                    // 1. If this is the active chat, append message
                    if (activeContact && activeAccount == accountId && (activeContact.toString() == identifier.toString() || activeContact == msg.contact_identifier)) {
                        let align = msg.direction === 'out' ? 'align-self-end bg-primary text-white' : 'align-self-start bg-white';
                        let timeLabel = msg.message_time ? msg.message_time.substring(0, 16) : '';
                        let html = `<div class="p-2 mb-2 rounded shadow-sm ${align}" style="max-width: 70%;">
                                        <div style="white-space: pre-wrap;">${msg.message}</div>
                                        <div class="text-end small ${msg.direction === 'out' ? 'text-white-50' : 'text-muted'}" style="font-size:0.7rem;">${timeLabel}</div>
                                    </div>`;
                        
                        $('#chat-messages .text-center, #chat-messages .text-muted').remove();
                        $('#chat-messages').append(html);
                        scrollToBottom();
                    }

                    // 2. Update the conversation list on the left
                    let $contactRow = $(`.contact-item[data-id="${identifier}"][data-account-id="${accountId}"]`);
                    if ($contactRow.length) {
                        $contactRow.find('small').text(msg.message_time);
                        $('#conversation-list').prepend($contactRow);
                    } else {
                        let newRow = `<a href="#" class="list-group-item list-group-item-action contact-item" data-id="${identifier}" data-account-id="${accountId}">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">${identifier}</h6>
                                            <small class="text-muted">${msg.message_time}</small>
                                        </div>
                                      </a>`;
                        $('#conversation-list').prepend(newRow);
                        
                        // Apply current filter
                        let activeTabAccountId = $('#accountTabs a.active').data('account-id');
                        if (activeTabAccountId !== 'all' && activeTabAccountId != accountId) {
                            $(`.contact-item[data-id="${identifier}"][data-account-id="${accountId}"]`).hide();
                        }
                    }
                }
            });
        }
    });
</script>
@endpush

