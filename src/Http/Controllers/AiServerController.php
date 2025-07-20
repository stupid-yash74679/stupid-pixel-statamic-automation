<?php

namespace StupidPixel\StatamicAutomation\Http\Controllers;

use Illuminate\Http\Request;
use Statamic\Http\Controllers\Controller;
use GuzzleHttp\Client;

class AiServerController extends Controller
{
    protected $apiKey;
    protected $client;

    public function __construct()
    {
        $this->apiKey = env('OPENAI_API_KEY'); // Assuming API key is stored in .env
        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function chat(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string',
            'model' => 'sometimes|string',
        ]);

        $model = $request->input('model', 'gpt-4o-mini'); // Default model

        try {
            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => $request->input('prompt')]
                    ],
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            
            if (isset($body['choices'][0]['message']['content'])) {
                return response()->json(['response' => $body['choices'][0]['message']['content']]);
            } else {
                return response()->json(['error' => 'Unexpected AI response format.'], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
}
