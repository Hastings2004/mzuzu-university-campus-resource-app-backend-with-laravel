<?php

namespace App\Services;

use Google\Cloud\Dialogflow\V2\SessionsClient;

class ChatbotService
{
    /**
     * Process a user message and return a chatbot reply.
     * In the future, this will call an NLP API and map intents to app logic.
     */
    public function getReply(string $message): string
    {
        $projectId = 'YOUR_PROJECT_ID'; // TODO: Replace with your Dialogflow project ID
        $sessionId = uniqid();
        $languageCode = 'en-US';
        $credentialsPath = storage_path('app/dialogflow-key.json'); // Path to your service account JSON

        $sessionsClient = new SessionsClient([
            'credentials' => $credentialsPath
        ]);
        $session = $sessionsClient->sessionName($projectId, $sessionId);

        $textInput = new \Google\Cloud\Dialogflow\V2\TextInput();
        $textInput->setText($message);
        $textInput->setLanguageCode($languageCode);

        $queryInput = new \Google\Cloud\Dialogflow\V2\QueryInput();
        $queryInput->setText($textInput);

        $response = $sessionsClient->detectIntent($session, $queryInput);
        $queryResult = $response->getQueryResult();
        $reply = $queryResult->getFulfillmentText();

        $sessionsClient->close();

        return $reply ?: 'Sorry, I could not understand that.';
    }
} 