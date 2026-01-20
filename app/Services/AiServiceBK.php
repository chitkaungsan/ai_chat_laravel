<?php

namespace App\Services;

use App\Models\Message;
use OpenAI\Laravel\Facades\OpenAI;

class AiServiceBK
{
    public function getTools(){
        return[
            [
                'type'=>'function',
                'function' =>[
                    'name' => 'sum_numbers',
                    'description' => 'Sum two numbers together',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'a' => ['type' => 'number', 'description' => 'first number'],
                            'b' => ['type' => 'number', 'description' => 'second number']
                        ],
                        'required' => ['a','b'],
                    ]
                ]
            ]
        ];
    }

    public function getContext($sessionId){
        $history = Message::where('session_id', $sessionId)
                    ->latest(10)
                    ->get()
                    ->reverse()
                    ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
                    ->toArray();

        return array_merge([
            ['role' => 'system', 'content' => 'Your professional helper']
        ], $history);
    }

    public function getStreamedResponseWithTools($sessionId, $userPrompt){
        Message::create([
            'session_id' => $sessionId,
            'role' => 'user',
            'content' => $userPrompt
        ]);

        $message = $this->getContext($sessionId);

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'message' => $message,
            'tools' => $this->getTools()
        ]);

        $message = $response->choices[0]->message;

        if ($message->toolCalls){
            foreach($message->toolCalls as $toolCall){
                $args = json_decode($toolCall->function->arguments, true);

                $result = $args['a'] + $args['b'];

        $messages[] = [
            'role' => 'assistant',
            'content' => $message->content ?? '',
            'tool_calls' => [
                [
                    'id' => $toolCall->id,
                    'type' => 'function',
                    'function' =>   [
                    'name' => $toolCall->function->name,
                    'arguments' => $toolCall->function->arguments,
                    ]
                ]
            ]
        ];

        $messages[] = [
            'role' => 'tool',
            'tool_call_id' => $toolCall->id,
            'content' => (string) $result,
        ];

        return OpenAI::chat()->createStreamed([
            'model' =>'gpt-4o',
            'messages' => $messages,
        ]);
            }
        }
    }
}
