<?php

namespace App\Services\Bitrix24;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class Bitrix24Service
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'verify' => false,
        ]);
    }

    public function call(string $method, array $params = []): array
    {
        try {
            $url = $this->getWebhookUrl($method);

            Log::debug('Bitrix24 API request', [
                'method' => $method,
                'url' => $url,
                'params' => $params,
            ]);

            $response = $this->client->post($url, ['json' => $params]);
            $result = json_decode($response->getBody(), true);

            if (isset($result['error'])) {
                Log::error('Bitrix24 API error', [
                    'method' => $method,
                    'error' => $result['error'],
                    'error_description' => $result['error_description'] ?? '',
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Bitrix24 API request failed', [
                'method' => $method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    private function getWebhookUrl(string $method): string
    {
        return match ($method) {
            'crm.lead.add' => config('services.bitrix24.webhook_add_url'),
            'crm.lead.update' => config('services.bitrix24.webhook_update_url'),
            'crm.lead.list' => config('services.bitrix24.webhook_list_url'),
            'crm.deal.add' => config('services.bitrix24.webhook_deal_add_url'),
            'crm.deal.update' => config('services.bitrix24.webhook_deal_update_url'),
            'crm.deal.list' => config('services.bitrix24.webhook_deal_list_url'),
            'user.get' => config('services.bitrix24.webhook_user_get_url'),
            'crm.status.list' => config('services.bitrix24.webhook_status_list_url'),
            'crm.deal.fields' => config('services.bitrix24.webhook_deal_fields_url'),
            'crm.contact.list' => config('services.bitrix24.webhook_contact_list_url'),
            default => config('services.bitrix24.webhook_add_url'),
        };
    }
}