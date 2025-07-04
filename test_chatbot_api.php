<?php

// Simple PHP script to test the chatbot API endpoint

$apiUrl = 'http://localhost:8000/api/chatbot';
$message = 'Is ICT Lab 2 free next Tuesday afternoon?';

$data = [
    'message' => $message,
];

$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
    ],
];

$context  = stream_context_create($options);
$result = file_get_contents($apiUrl, false, $context);

if ($result === FALSE) {
    echo "Error contacting chatbot API.\n";
} else {
    echo "Response from chatbot:\n";
    echo $result . "\n";
} 