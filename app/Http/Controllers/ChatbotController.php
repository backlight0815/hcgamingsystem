<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Keyword;



    class ChatbotController extends Controller
    {
        public function handleRequest(Request $request)
        {
            $message = $request->input('message');

            // Retrieve response from the Keyword model
            $response = Keyword::getResponseForMessage($message);

            // Return the response as JSON
            return response()->json(['message' => $response]);
        }

        private function getKeywordResponse($response)
        {
            // Logic to check if the response contains a keyword response
            // You can implement this logic based on your requirements
            // For now, let's return null
            return null;
        }



    public function index()
    {
        // Logic for handling GET requests to /chatbot
        return view('agent.chatbot.chatbot_all'); // or return any other response you desire
    }

    private function processMessage($message)
    {
        // Simple rule-based chatbot logic
        $responses = [
            'hi' => 'Hello! How can I help you?',
            'how are you' => 'I am just a bot, but thank you for asking!',
            'bye' => 'Goodbye! Have a great day!',
            // Add more rules and responses as needed
        ];

        // Check if the message matches any rule
        foreach ($responses as $pattern => $response) {
            if (stripos($message, $pattern) !== false) {
                return $response;
            }
        }

        // If no rule matches, provide a default response
        return "I'm sorry, I didn't understand that.";
    }
}
