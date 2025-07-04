<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\ChatbotService;

class ChatbotController extends Controller
{
    protected $chatbotService;

    public function __construct(ChatbotService $chatbotService)
    {
        $this->chatbotService = $chatbotService;
    }

    /**
     * Handle incoming chatbot messages.
     */
    public function handle(Request $request): JsonResponse
    {
        $message = $request->input('message');
        $reply = $this->chatbotService->getReply($message);
        return response()->json([
            'reply' => $reply
        ]);
    }
} 