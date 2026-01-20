<?php

namespace App\Http\Controllers;
use App\Services\AiServiceBK;

use Illuminate\Http\Request;

class ChatBKController extends Controller
{
    public function __invoke(Request $request, AiServiceBK $aiService)
    {
        // Use session from request or default to 123
        $sessionId = $request->input('session_id', 123);
        $prompt = $request->input('prompt');

        if (!$prompt) {
            return response()->json(['error' => 'Prompt is required'], 400);
        }

        $stream = $aiService->getStreamedResponseWithTools($sessionId, $prompt);

        return response()->stream(function () use ($stream) {
            foreach ($stream as $response) {
                // OpenAI streaming chunks might have null content in the first/last delta
                $text = $response->choices[0]->delta->content ?? '';

                if ($text !== '') {
                    echo "data: " . json_encode(['text' => $text]) . "\n\n";

                    // Force the output to the browser/Postman immediately
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            }

            // Signal that the stream is finished
            echo "data: [DONE]\n\n";
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no', // Useful for Nginx servers
            'Cache-Control' => 'no-cache',
        ]);
    }
}
