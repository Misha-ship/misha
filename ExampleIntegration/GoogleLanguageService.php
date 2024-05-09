<?php

namespace App\Jobs\Moderation;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use function App\Http\Service\GoogleML\env;

class GoogleLanguageService
{
    private Client $httpClient;
    private mixed $apiKey;
    private string $baseEndpoint;
    private string $baseEndpointV1;

    public function __construct()
    {
        $this->httpClient = new Client();
        $this->apiKey = env('GOOGLE_API_KEY');
        $this->baseEndpoint = "https://language.googleapis.com/v2/documents";
        $this->baseEndpointV1 = "https://language.googleapis.com/v1/documents";
    }

    private function makeRequest($type, $text)
    {
        $endpoint = $this->baseEndpoint . ':' . $type . '?key=' . $this->apiKey;
        $body = [
            'document' => [
                'type' => 'PLAIN_TEXT',
                'content' => $text,
            ],
        ];

        try {
            $response = $this->httpClient->post($endpoint, [
                'json' => $body,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::info($e->getMessage());
            return null;
        }
    }

    private function makeRequestV1($type, $text)
    {
        $endpoint = $this->baseEndpointV1 . ':' . $type . '?key=' . $this->apiKey;
        $body = [
            'classificationModelOptions' => [
                'v2Model' => [
                    'contentCategoriesVersion' => 'V2'
                ]
            ],
            'document' => [
                'type' => 'PLAIN_TEXT',
                'content' => $text,
            ],
        ];

        try {
            $response = $this->httpClient->post($endpoint, [
                'json' => $body,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            Log::info($e->getMessage());
            return null;
        }
    }

    public function moderateText($text)
    {
        return $this->makeRequest('moderateText', $text);
    }

    public function analyzeEntities($text)
    {
        return $this->makeRequest('analyzeEntities', $text);
    }

    public function analyzeSentiment($text)
    {
        return $this->makeRequest('analyzeSentiment', $text);
    }

    public function classifyText($text)
    {
        return $this->makeRequestV1('classifyText', $text);
    }
}
