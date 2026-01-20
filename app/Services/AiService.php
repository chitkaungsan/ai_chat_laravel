<?php

namespace App\Services;

use App\Models\Message;
use OpenAI\Laravel\Facades\OpenAI;

class AiService
{
    /**
     * Define the functions (tools) that the AI can choose to call.
     */
    public function getTools()
    {
        return [
            [
                'type' => 'function', // Specify this is a function call
                'function' => [
                    'name' => 'sum_numbers', // The name the AI will use to call it
                    'description' => 'Sum two numbers together', // Helps the AI understand WHEN to use it
                    'parameters' => [
                        'type' => 'object', // Parameters must be an object
                        'properties' => [
                            'a' => ['type' => 'number', 'description' => 'First number'],
                            'b' => ['type' => 'number', 'description' => 'Second number'],
                        ],
                        'required' => ['a', 'b'], // Ensure the AI provides both numbers
                    ],
                ],
            ]
        ];
    }

    /**
     * Retrieve the conversation history to give the AI memory.
     */
    public function getContext($sessionId)
    {
        // 1. Fetch the 10 most recent messages for this session from the database
        $history = Message::where('session_id', $sessionId)
            ->latest()
            ->take(10)
            ->get()
            // 2. Reverse them so they are in chronological order (oldest to newest)
            ->reverse()
            // 3. Map the Eloquent models into the simple array format OpenAI expects
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        // 4. Prepend the 'system' message to set the AI's persona/behavior
        return array_merge([
            ['role' => 'system', 'content' => 'You are a professional helper.']
        ], $history);
    }

    /**
     * The core logic: checks for tool calls, executes them, and returns a stream.
     */
    public function getStreamedResponseWithTools($sessionId, $userPrompt)
    {
        // 1. Persist the user's new message to the database immediately
        Message::create([
            'session_id' => $sessionId,
            'role' => 'user',
            'content' => $userPrompt
        ]);

        // 2. Build the message array including the system prompt and history
        $messages = $this->getContext($sessionId);

        // 3. First API call (Non-Streamed) to see if AI wants to use a tool
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => $messages,
            'tools' => $this->getTools(), // Pass the tool definitions
        ]);

        // 4. Capture the assistant's response message
        $message = $response->choices[0]->message;

        // 5. Check if the AI returned a 'tool_calls' request instead of regular text
        if ($message->toolCalls) {
            foreach ($message->toolCalls as $toolCall) {
                // a. Decode the JSON arguments provided by the AI
                $args = json_decode($toolCall->function->arguments, true);

                // b. Execute the actual PHP logic (the "tool")
                $result = $args['a'] + $args['b'];

                // c. IMPORTANT: Add the Assistant's tool request to the history.
                // We use ?? '' to ensure 'content' is never null, avoiding API errors.
                $messages[] = [
                    'role' => 'assistant',
                    'content' => $message->content ?? '',
                    'tool_calls' => [
                        [
                            'id' => $toolCall->id,
                            'type' => 'function',
                            'function' => [
                                'name' => $toolCall->function->name,
                                'arguments' => $toolCall->function->arguments,
                            ],
                        ]
                    ],
                ];

                // d. Add the Tool's output (the result) to the history
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $toolCall->id, // Links the result to the specific call
                    'content' => (string) $result // Content must be a string
                ];
            }
        }

        // 6. Final API call (Streamed) using the updated message history
        // This generates the final conversational response (e.g., "The sum is 40")
        return OpenAI::chat()->createStreamed([
            'model' => 'gpt-4o',
            'messages' => $messages,
        ]);
    }
}
