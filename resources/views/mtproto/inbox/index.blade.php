@extends('layouts.auth')
@section('title', __('Unified Inbox'))
@section('content')
<div class="main-content container-fluid">
    <div class="page-title pb-3">
        <h3>{{__('Telegram CRM Inbox')}}</h3>
    </div>

    <div class="row" style="height: 70vh;">
        <!-- Conversation List -->
        <div class="col-md-4 h-100">
            <div class="card h-100 overflow-auto">
                <div class="card-header"><h4>{{__('Chats')}}</h4></div>
                <div class="list-group list-group-flush" id="conversation-list">
                    @foreach($conversations as $chat)
                        <a href="#" class="list-group-item list-group-item-action contact-item" data-id="{{$chat->contact_identifier}}">
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
                <div class="card-header bg-light"><h5 id="chat-title">{{__('Select a chat to start messaging')}}</h5></div>
                <div class="card-body overflow-auto d-flex flex-column" id="chat-messages" style="background: #f0f2f5;">
                    <!-- Messages will appear here -->
                </div>
                <div class="card-footer">
                    <form id="reply-form" class="d-none">
                        <div class="input-group">
                            <input type="text" id="message-input" class="form-control" placeholder="{{__('Type your reply...')}}">
                            <button class="btn btn-primary" type="submit"><i class="fas fa-paper-plane"></i></button>
                        </div>
                    </form>
                    @if($is_admin)
                        <div class="small text-muted mt-1"><i class="fas fa-info-circle"></i> {{__('Admin Mode: Replying from the first active system account.')}}</div>
                    @endif
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
    const baseUrl = "{{ url('/') }}";

    $(document).on('click', '.contact-item', function(e) {
        e.preventDefault();
        $('.contact-item').removeClass('active bg-light');
        $(this).addClass('active bg-light');
        
        activeContact = $(this).data('id').toString();
        $('#chat-title').text('Chat with ' + activeContact);
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
                message: message
            },
            success: function(res) {
                $input.val('').prop('disabled', false);
                $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i>');

                if(res.success && res.message_obj) {
                    let msg = res.message_obj;
                    let timeLabel = msg.message_time ? msg.message_time.substring(0, 16) : '';
                    let html = `<div class="p-2 mb-2 rounded shadow-sm align-self-end bg-primary text-white" style="max-width: 70%;">
                                    <div style="white-space: pre-wrap;">${msg.message}</div>
                                    <div class="text-end small text-white-50" style="font-size:0.7rem;">${timeLabel}</div>
                                </div>`;
                    // Remove "No messages found" placeholder if present
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

    // Auto-load first contact on page load spent
    $(document).ready(function() {
        let $first = $('.contact-item').first();
        if ($first.length) {
            $first.trigger('click');
        }

        // Real-time Inbox Updates
        if (typeof global_mtproto_channel !== 'undefined' && global_mtproto_channel !== null) {
            global_mtproto_channel.bind('mtproto-realtime-event', function(data) {
                if (data.type == 'message') {
                    let msg = data.payload.message;
                    let identifier = data.payload.identifier;

                    // 1. If this is the active chat, append message
                    // Fuzzy matching: "123456" == "@username" if that's how it's stored
                    if (activeContact && (activeContact.toString() == identifier.toString() || activeContact == msg.contact_identifier)) {
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
                    let $contactRow = $(`.contact-item[data-id="${identifier}"]`);
                    if ($contactRow.length) {
                        // Update time and move to top
                        $contactRow.find('small').text(msg.message_time);
                        $('#conversation-list').prepend($contactRow);
                    } else {
                        // New conversation, add to top
                        let newRow = `<a href="#" class="list-group-item list-group-item-action contact-item" data-id="${identifier}">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">${identifier}</h6>
                                            <small class="text-muted">${msg.message_time}</small>
                                        </div>
                                      </a>`;
                        $('#conversation-list').prepend(newRow);
                    }
                }
            });
        }
    });
</script>
@endpush

