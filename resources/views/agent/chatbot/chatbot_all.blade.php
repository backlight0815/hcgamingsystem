@extends('admin.admin_master')
@section('admin')
<style>
    /* Add custom styles for the chatbot */
    .chat-container {
        max-width: 600px;
        margin: 0 auto;
    }
    .chat-messages {
        list-style-type: none;
        padding: 0;
        margin: 0;
    }
    .chat-message {
        margin-bottom: 10px;
    }
    .user-message {
        text-align: right;
    }
    .bot-message {
        text-align: left;
    }
    .chat-message-container {
        overflow: hidden;
    }
    .user-message .chat-message-content {
        float: right;
    }
    .bot-message .chat-message-content {
        float: left;
    }
</style>
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="page-content">
    <div class="container-fluid">
        <div class="chat-container">
            <h2>Chatbot</h2>
            <div id="chat" class="chat-messages">
                <!-- Chat messages will be displayed here -->
            </div>
            <form id="chatForm" method="POST"> <!-- Ensure the method attribute is set to POST -->
                <div class="mb-3">
                    <label for="message" class="form-label">Message:</label>
                    <input type="text" id="message" class="form-control" placeholder="Type your message">
                </div>
                <button type="submit" class="btn btn-primary">Send</button>
            </form>
        </div>
    </div>
</div>

<script>
    // JavaScript for chatbot functionality
    document.getElementById('chatForm').addEventListener('submit', function(event) {
        event.preventDefault();
        var message = document.getElementById('message').value;
        sendMessage(message);
    });

    function sendMessage(message) {
        // Add user message to chat
        var chat = document.getElementById('chat');
        var userMessage = '<div class="chat-message user-message">' +
                            '<div class="chat-message-container">' +
                                '<div class="chat-message-content">' +
                                    message +
                                '</div>' +
                            '</div>' +
                          '</div>';
        chat.innerHTML += userMessage;

        // Send message to server for processing
        fetch('/chatbot', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ message: message })
        })
        .then(response => response.json())
        .then(data => {
            // Add bot response to chat
            var botMessage = '<div class="chat-message bot-message">' +
                                '<div class="chat-message-container">' +
                                    '<div class="chat-message-content">' +
                                        data.message +
                                    '</div>' +
                                '</div>' +
                             '</div>';
            chat.innerHTML += botMessage;

            // Check if bot message contains a keyword to trigger an automatic response
            if (data.keywordResponse) {
                // Add bot response to chat
                var keywordBotMessage = '<div class="chat-message bot-message">' +
                                            '<div class="chat-message-container">' +
                                                '<div class="chat-message-content">' +
                                                    data.keywordResponse +
                                                '</div>' +
                                            '</div>' +
                                         '</div>';
                chat.innerHTML += keywordBotMessage;
            }

            // Clear input field
            document.getElementById('message').value = '';
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
</script>
@endsection
