<?php
namespace App\Services\Bitrix24;

use Illuminate\Support\Facades\Log;

class LeadService
{
    private Bitrix24Service $client;

    public function __construct(Bitrix24Service $client)
    {
        $this->client = $client;
    }

    public function create(array $fields): ?int
    {
        Log::info('➡️ LeadService::create', ['fields' => $fields]);

        // To Do product duco
        if ($fields['UF_CRM_1786433126'] !== 'duco' ) {
            $response = $this->client->call('crm.lead.add', ['fields' => $fields]);

            if (isset($response['error'])) {
                Log::error('❌ Failed to create lead', [
                    'error' => $response['error'],
                    'error_description' => $response['error_description'] ?? '',
                    'fields' => $fields,
                ]);
                return null;
            }

            Log::info('✅ Lead created', ['lead_id' => $response['result'] ?? null]);
        }
        return $response['result'] ?? null;
    }

    public function update(int $leadId, array $fields): bool
    {
        Log::info('➡️ LeadService::update', ['lead_id' => $leadId]);

        $response = $this->client->call('crm.lead.update', [
            'id' => $leadId,
            'fields' => $fields,
        ]);

        if (isset($response['error'])) {
            Log::error('❌ Failed to update lead', [
                'lead_id' => $leadId,
                'error' => $response['error'],
                'error_description' => $response['error_description'] ?? '',
                'fields' => $fields,
            ]);
            return false;
        }

        Log::info('✅ Lead updated', ['lead_id' => $leadId]);
        return true;
    }

    public function getFields(): array
    {
        $response = $this->client->call('crm.lead.fields');
        return $response['result'] ?? [];
    }
}
