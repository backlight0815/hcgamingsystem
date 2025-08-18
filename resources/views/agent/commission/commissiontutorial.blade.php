@extends('admin.admin_master')
@section('admin')
<style>
    /* Your existing styles */

    /* Style for sender (user) message */
    .user-message .sender-icon {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        margin-right: 10px;
    }

    /* Style for AI (bot) message */
    .bot-message .ai-icon {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        margin-right: 10px;
    }
</style>
<!-- Add your custom JavaScript for chatbot functionality -->
<script>
    // JavaScript for chatbot functionality
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('chatForm').addEventListener('submit', function(event) {
            event.preventDefault();
            var message = document.getElementById('message').value;
            sendMessage(message);
        });

        function sendMessage(message) {
            // Add user message to chat
            var chat = document.getElementById('chat');
            var userMessage = '<div class="chat-message user-message">' +
                                '<img src="user-icon.png" alt="User Icon" class="sender-icon">' +
                                '<strong>You:</strong> ' + message +
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
                                    '<img src="ai-icon.png" alt="AI Icon" class="ai-icon">' +
                                    '<strong>Bot:</strong> ' + data.message +
                                 '</div>';
                chat.innerHTML += botMessage;

                // Clear input field
                document.getElementById('message').value = '';
            });
        }
    });
</script>

<div class="page-content">
    <div class="container-fluid">
        <!-- Existing content -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0">Commission Tutorial</h4>
                    <button class="btn btn-success waves-effect waves-light" type="button" data-bs-toggle="modal" data-bs-target="#tutorialModal">How to earn commission</button>
                    <!-- breadcrumbData  -->
                </div>
            </div>
        </div>
    </div>
</div>
<!-- end page title -->

<!-- Chatbot container -->
<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="chat-container">
                <h2>Chatbot</h2>
                <div id="chat" class="chat-messages">
                    <!-- Chat messages will be displayed here -->
                </div>
                <form id="chatForm">
                    <div class="mb-3">
                        <label for="message" class="form-label">Message:</label>
                        <input type="text" id="message" class="form-control" placeholder="Type your message">
                    </div>
                    <button type="submit" class="btn btn-primary">Send</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
