<?php

namespace App\Services\Bitrix24;

use Illuminate\Support\Facades\Log;

class DealService
{
    private Bitrix24Service $client;

    public function __construct(Bitrix24Service $client)
    {
        $this->client = $client;
    }

    public function create(array $fields): ?int
    {
        Log::info('➡️ DealService::create', ['fields' => $fields]);

        $response = $this->client->call('crm.deal.add', ['fields' => $fields]);

        if (isset($response['error'])) {
            Log::error('❌ Failed to create deal', [
                'error' => $response['error'],
                'error_description' => $response['error_description'] ?? '',
                'fields' => $fields,
            ]);
            return null;
        }

        Log::info('✅ Deal created', ['deal_id' => $response['result'] ?? null]);
        return $response['result'] ?? null;
    }

    public function update(int $dealId, array $fields): bool
    {
        Log::info('➡️ DealService::update', ['deal_id' => $dealId]);

        $response = $this->client->call('crm.deal.update', [
            'id' => $dealId,
            'fields' => $fields,
        ]);

        if (isset($response['error'])) {
            Log::error('❌ Failed to update deal', [
                'deal_id' => $dealId,
                'error' => $response['error'],
                'error_description' => $response['error_description'] ?? '',
                'fields' => $fields,
            ]);
            return false;
        }

        Log::info('✅ Deal updated', ['deal_id' => $dealId]);
        return true;
    }

    public function getFields(): array
    {
        Log::info('➡️ DealService::getFields');

        $response = $this->client->call('crm.deal.fields');

        Log::info('⬅️ DealService::getFields response', ['response' => $response]);

        return $response['result'] ?? [];
    }

    public function getStatuses(): array
    {
        Log::info('➡️ DealService::getStatuses');

        $response = $this->client->call('crm.status.list', [
            'filter' => ['ENTITY_ID' => 'DEAL_STAGE_6'],
        ]);

        Log::info('⬅️ DealService::getStatuses response', ['response' => $response]);

        return $response['result'] ?? [];
    }

    /**
     * ✅ НОВЫЙ МЕТОД: получение списка сделок
     */
    public function list(array $filter = [], int $start = 0): array
    {
        Log::info('➡️ DealService::list', ['filter' => $filter, 'start' => $start]);

        $response = $this->client->call('crm.deal.list', [
            'filter' => $filter,
            'select' => ['ID', 'TITLE', 'DATE_CREATE', 'DATE_MODIFY', 'STAGE_ID', 'OPPORTUNITY', 'UF_CRM_1785827564', 'ASSIGNED_BY_ID', 'CONTACT_ID'],
            'order' => ['DATE_CREATE' => 'DESC'],
            'start' => $start,
        ]);

        if (isset($response['error'])) {
            Log::error('❌ Failed to get deals list', [
                'error' => $response['error'],
                'error_description' => $response['error_description'] ?? '',
            ]);
            return [];
        }

        Log::info('✅ Deals retrieved', ['count' => count($response['result'] ?? [])]);

        return $response['result'] ?? [];
    }
}