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
                        <a href="#" class="list-group-item list-group-item-action contact-item p-3" data-id="{{$chat->contact_identifier}}" data-account-id="{{$chat->account_id}}">
                            <div class="d-flex w-100 justify-content-between align-items-center position-relative">
                                <div>
                                    <h6 class="mb-1">{{$chat->contact_identifier}}</h6>
                                    <small class="text-muted d-block" style="font-size: 0.75rem;">{{$chat->last_msg}}</small>
                                </div>
                                <div class="text-end d-flex align-items-center">
                                    <span class="badge bg-success rounded-pill unread-badge me-2 {{ $chat->unread_count > 0 ? '' : 'd-none' }}" style="font-size: 0.7rem;">{{$chat->unread_count}}</span>
                                    <button class="btn btn-sm btn-outline-danger delete-chat-btn border-0 py-0 px-1" title="Delete Conversation" onclick="deleteConversation(event, '{{$chat->contact_identifier}}', {{$chat->account_id}})">
                                        <i class="fas fa-trash-alt" style="font-size: 0.8rem;"></i>
                                    </button>
                                </div>
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
                    <form id="reply-form" class="d-none" enctype="multipart/form-data">
                        <input type="hidden" id="active-account-id" value="">
                        <input type="file" id="media-input" class="d-none" accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.zip">
                        <div class="input-group align-items-center">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle no-caret" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 5px 0 0 5px; height: 38px;">
                                    <i class="fas fa-paperclip"></i>
                                </button>
                                <ul class="dropdown-menu shadow">
                                    <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="triggerMedia('photo')"><i class="fas fa-image me-2 text-primary"></i> Send Photo</a></li>
                                    <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="triggerMedia('video')"><i class="fas fa-video me-2 text-danger"></i> Send Video</a></li>
                                    <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="triggerMedia('document')"><i class="fas fa-file-alt me-2 text-info"></i> Send Document</a></li>
                                </ul>
                            </div>
                            <input type="text" id="message-input" class="form-control" placeholder="{{__('Type your reply...')}}" style="height: 38px;">
                            <button class="btn btn-primary" type="submit" style="height: 38px;"><i class="fas fa-paper-plane"></i></button>
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

        // Mark as Read
        $.post("{{route('mtproto.inbox.read')}}", {
            _token: "{{csrf_token()}}",
            identifier: identifier,
            account_id: activeAccount
        });

        // Clear badge locally
        $(`.contact-item[data-id="${identifier}"][data-account-id="${activeAccount}"] .unread-badge`).addClass('d-none').text('0');

        $.ajax({
            url: baseUrl + "/mtproto/inbox/messages/" + encodeURIComponent(identifier),
            method: 'GET',
            success: function(messages) {
                let html = '';
                if(!messages || messages.length === 0) {
                    html = '<div class="text-center my-auto text-muted">No messages found.</div>';
                } else {
                    messages.forEach(function(msg) {
                        html += getMessageHtml(msg);
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

    function deleteMsg(id, type) {
        let confirmText = type === 'everyone' ? "Are you sure you want to delete this message for everyone?" : "Delete for me only? This will still be visible to the recipient.";
        if(!confirm(confirmText)) return;

        $.ajax({
            url: "{{route('mtproto.inbox.delete')}}",
            method: 'POST',
            data: {
                _token: "{{csrf_token()}}",
                message_id: id,
                type: type
            },
            success: function(res) {
                if(res.success) {
                    $(`.msg-item[data-id="${id}"]`).fadeOut(300, function() { $(this).remove(); });
                } else {
                    alert(res.error || "Failed to delete message");
                }
            },
            error: function(xhr) {
                alert("Error deleting message. Please login again if session expired.");
            }
        });
    }

    function getMessageHtml(msg) {
        let align = msg.direction === 'out' ? 'align-self-end bg-primary text-white' : 'align-self-start bg-white';
        let timeLabel = msg.message_time ? msg.message_time.substring(0, 16) : '';
        let ticks = '';
        if (msg.direction === 'out') {
            ticks = msg.is_read 
                ? ' <i class="fa fa-check-double text-success ms-1 tick-icon"></i>' 
                : ' <i class="fa fa-check text-white-50 ms-1 tick-icon"></i>';
        }
        
        // Refined Delete Button: 3 Horizontal Dots, Bold, White, Larger
        let deleteBtn = '';
        if (msg.direction === 'out') {
            deleteBtn = `<div class="dropdown msg-options" style="position: absolute; top: 5px; right: 5px; opacity: 0; transition: all 0.2s ease;">
                            <button class="btn btn-link btn-sm text-white p-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="text-decoration: none; line-height: 1;">
                                <i class="fas fa-ellipsis-h" style="font-size: 1.2rem; filter: drop-shadow(0px 0px 1px rgba(0,0,0,0.5));"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" style="font-size: 0.85rem; border-radius: 10px;">
                                <li><a class="dropdown-item text-danger fw-bold py-2" href="javascript:void(0)" onclick="deleteMsg(${msg.id}, 'everyone')"><i class="fas fa-trash-alt me-2"></i> ${baseUrl.includes('telegroupbot') ? 'Haye ye Delete Everyone' : 'Delete for Everyone'}</a></li>
                                <li><a class="dropdown-item py-2" href="javascript:void(0)" onclick="deleteMsg(${msg.id}, 'me')"><i class="fas fa-eraser me-2"></i> ${baseUrl.includes('telegroupbot') ? 'Sirf Mere Liye' : 'Delete for Me'}</a></li>
                            </ul>
                         </div>`;
        }

        let messageContent = msg.message;
        if (msg.media_path) {
            // Handle both absolute paths (legacy) and relative paths (new)
            let relativePath = msg.media_path.includes('public/') 
                ? msg.media_path.split('public/')[1] 
                : msg.media_path;
            
            // Refined URL generation: avoid double /storage or missing /public
            let cleanBase = baseUrl.endsWith('/') ? baseUrl.slice(0, -1) : baseUrl;
            let fileUrl = cleanBase + '/storage/' + relativePath;
            
            // Force fix if the user's URL still includes /public/ but the link is at /storage/
            if (fileUrl.includes('/public/storage/')) {
                fileUrl = fileUrl.replace('/public/storage/', '/storage/');
            }
            
            if (msg.media_type === 'photo') {
                messageContent = `<div class="mb-2"><img src="${fileUrl}" class="img-fluid rounded border shadow-sm media-preview" style="max-height: 250px; cursor: pointer;" onclick="window.open('${fileUrl}')"></div>`;
                if (msg.message && !msg.message.includes('[Photo Received]') && !msg.message.includes('[Photo Sent]')) {
                    messageContent += `<div>${msg.message}</div>`;
                }
            } else if (msg.media_type === 'video') {
                messageContent = `<div class="mb-2">
                                    <div class="d-flex align-items-center p-2 bg-dark rounded text-white" style="cursor: pointer;" onclick="window.open('${fileUrl}')">
                                        <i class="fas fa-play-circle fa-2x me-2 text-primary"></i>
                                        <div>
                                            <div class="small fw-bold">Video File</div>
                                            <div class="text-white-50" style="font-size: 0.6rem;">Click to view/download</div>
                                        </div>
                                    </div>
                                  </div>`;
                if (msg.message && !msg.message.includes('[Video Received]') && !msg.message.includes('[Video Sent]')) {
                    messageContent += `<div>${msg.message}</div>`;
                }
            } else {
                messageContent = `<div class="mb-2">
                                    <a href="${fileUrl}" target="_blank" class="text-decoration-none d-flex align-items-center p-2 bg-light rounded border text-dark">
                                        <i class="fas fa-file-alt fa-2x me-2 text-secondary"></i>
                                        <div style="overflow: hidden;">
                                            <div class="small fw-bold text-truncate">${msg.media_path.split(/[\\/]/).pop()}</div>
                                            <div class="text-muted" style="font-size: 0.6rem;">Download File</div>
                                        </div>
                                    </a>
                                  </div>`;
                if (msg.message && !msg.message.includes('[Document Received]') && !msg.message.includes('[Document Sent]')) {
                    messageContent += `<div>${msg.message}</div>`;
                }
            }
        } else {
            // Legacy/Text fallback
            if (msg.message === '[Photo Sent]') messageContent = '<i class="fas fa-image me-1"></i> ' + msg.message;
            if (msg.message === '[Video Sent]') messageContent = '<i class="fas fa-video me-1"></i> ' + msg.message;
            if (msg.message === '[Document Sent]') messageContent = '<i class="fas fa-file-alt me-1"></i> ' + msg.message;
        }

        return `<div class="p-2 mb-2 rounded shadow-sm ${align} msg-item position-relative" data-id="${msg.id}" style="max-width: 70%; min-width: 90px; padding-top: 10px !important;">
                    ${deleteBtn}
                    <div style="white-space: pre-wrap; padding-right: 18px; margin-top: 2px;">${messageContent}</div>
                    <div class="text-end small ${msg.direction === 'out' ? 'text-white-50' : 'text-muted'}" style="font-size:0.7rem; margin-top: 3px;">${timeLabel}${ticks}</div>
                 </div>`;
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
                    $('#chat-messages .text-muted').remove();
                    $('#chat-messages').append(getMessageHtml(res.message_obj));
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

    function deleteConversation(event, identifier, accountId) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        if (confirm("Are you sure you want to delete this conversation? This will delete all messages and media for this contact from your database.")) {
            $.post("{{route('mtproto.inbox.delete_conversation')}}", {
                _token: "{{csrf_token()}}",
                identifier: identifier,
                account_id: accountId
            }, function(response) {
                if (response.success) {
                    $(`.contact-item[data-id="${identifier}"][data-account-id="${accountId}"]`).fadeOut(300, function() {
                        $(this).remove();
                    });
                    
                    if (activeContact == identifier && activeAccount == accountId) {
                        $('#chat-messages').html('<div class="text-center my-auto text-muted">Conversation deleted.</div>');
                        $('#chat-title').text('Select a chat');
                        $('#active-account-badge').addClass('d-none');
                        $('#reply-form').addClass('d-none');
                        activeContact = null;
                        activeAccount = null;
                    }
                } else {
                    alert(response.error || "Failed to delete conversation");
                }
            }).fail(function() {
                alert("Server error while deleting conversation");
            });
        }
    }

    let currentMediaType = 'document';
    function triggerMedia(type) {
        currentMediaType = type;
        let accept = "*/*";
        if(type === 'photo') accept = "image/*";
        if(type === 'video') accept = "video/*";
        $('#media-input').attr('accept', accept).click();
    }

    $('#media-input').on('change', function() {
        let file = this.files[0];
        if(!file || !activeContact) return;

        let formData = new FormData();
        formData.append('_token', "{{csrf_token()}}");
        formData.append('identifier', activeContact);
        formData.append('account_id', activeAccount);
        formData.append('media', file);
        formData.append('media_type', currentMediaType);

        let $btn = $('#reply-form button[type="submit"]');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: "{{route('mtproto.inbox.send_media')}}",
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i>');
                $('#media-input').val('');
                if(res.success && res.message_obj) {
                    $('#chat-messages .text-muted').remove();
                    $('#chat-messages').append(getMessageHtml(res.message_obj));
                    scrollToBottom();
                } else {
                    alert(res.error || "Failed to send media");
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i>');
                let err = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : "Upload failed (Status: " + xhr.status + ")";
                alert(err);
                console.error("Upload error details:", xhr);
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
        function bindMtprotoEvents() {
            if (typeof global_mtproto_channel !== 'undefined' && global_mtproto_channel !== null) {
                console.log("MTProto Real-time: Channel found, binding events...");
                global_mtproto_channel.bind('mtproto-realtime-event', function(data) {
                    console.log("MTProto Real-time Event received:", data.type, data);
                    
                    if (data.type == 'message') {
                        let msg = data.payload.message;
                        let identifier = data.payload.identifier;
                        let accountId = msg.account_id;

                        // 1. If this is the active chat, append message
                        if (activeContact && activeAccount == accountId && (activeContact.toString() == identifier.toString() || activeContact == msg.contact_identifier)) {
                            $('#chat-messages .text-center, #chat-messages .text-muted').remove();
                            $('#chat-messages').append(getMessageHtml(msg));
                            scrollToBottom();
                        }

                        // 2. Update the conversation list on the left
                        let $contactRow = $(`.contact-item[data-id="${identifier}"][data-account-id="${accountId}"]`);
                        if ($contactRow.length) {
                            $contactRow.find('small').text(msg.message_time);
                            
                            // Increment unread count if not active
                            if (!(activeContact && activeAccount == accountId && (activeContact.toString() == identifier.toString()))) {
                                let $badge = $contactRow.find('.unread-badge');
                                let count = parseInt($badge.text()) || 0;
                                $badge.text(count + 1).removeClass('d-none');
                            }

                            $('#conversation-list').prepend($contactRow);
                        } else {
                            let newRow = `<a href="#" class="list-group-item list-group-item-action contact-item p-3" data-id="${identifier}" data-account-id="${accountId}">
                                            <div class="d-flex w-100 justify-content-between align-items-center position-relative">
                                                <div>
                                                    <h6 class="mb-1">${identifier}</h6>
                                                    <small class="text-muted d-block" style="font-size: 0.75rem;">${msg.message_time}</small>
                                                </div>
                                                <div class="text-end d-flex align-items-center">
                                                    <span class="badge bg-success rounded-pill unread-badge me-2 ${unreadClass}" style="font-size: 0.7rem;">${unreadVal}</span>
                                                    <button class="btn btn-sm btn-outline-danger delete-chat-btn border-0 py-0 px-1" title="Delete Conversation" onclick="deleteConversation(event, '${identifier}', ${accountId})">
                                                        <i class="fas fa-trash-alt" style="font-size: 0.8rem;"></i>
                                                    </button>
                                                </div>
                                            </div>
                                          </a>`;
                            $('#conversation-list').prepend(newRow);
                            
                            // Apply current filter
                            let activeTabAccountId = $('#accountTabs a.active').data('account-id');
                            if (activeTabAccountId !== 'all' && activeTabAccountId != accountId) {
                                $(`#conversation-list .contact-item[data-id="${identifier}"][data-account-id="${accountId}"]`).hide();
                            }
                        }
                    }

                    if (data.type == 'message-deleted') {
                        let msgId = data.payload.message_id;
                        $(`.msg-item[data-id="${msgId}"]`).fadeOut(300, function() { $(this).remove(); });
                    }

                    if (data.type == 'message-read') {
                        let messageIds = data.payload.message_ids;
                        let accountId = data.payload.account_id;
                        let identifier = data.payload.identifier;

                        if (activeAccount == accountId) {
                            messageIds.forEach(function(id) {
                                let $msg = $(`.msg-item[data-id="${id}"]`);
                                if ($msg.length) {
                                    $msg.find('.tick-icon')
                                        .removeClass('fa-check text-white-50')
                                        .addClass('fa-check-double text-success');
                                }
                            });
                        }
                    }
                });
            } else {
                console.warn("MTProto Real-time: Channel not ready, retrying in 500ms...");
                setTimeout(bindMtprotoEvents, 500);
            }
        }

        bindMtprotoEvents();
    });
</script>
<style>
    .msg-item:hover .msg-options { opacity: 1 !important; visibility: visible !important; }
    .msg-options button:hover { background: rgba(255,255,255,0.2); border-radius: 50%; }
    .msg-options button:focus { box-shadow: none; }
    .no-caret::after { display: none !important; }
    .attachment-preview { max-width: 200px; border-radius: 8px; margin-bottom: 5px; cursor: pointer; }
    
    /* Delete Chat Button */
    .delete-chat-btn { opacity: 0; transition: opacity 0.2s; }
    .contact-item:hover .delete-chat-btn { opacity: 1; }
    .contact-item.active .delete-chat-btn { opacity: 1; color: #dc3545; }
</style>
@endpush
