<?php
namespace App\Services\Bitrix24;

use Carbon\Carbon;
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

    /** Поиск дублирования лида по полям  */
    function findLeadDuplicatesWithFilter(array $phones): bool
    {
        $additionalFilter = [
            '>DATE_CREATE'   => '2026-01-01T00:00:00+03:00', // Только созданные в 2026 году
            'STATUS_SEMANTIC_ID' => 'P',                     // Только в промежуточных/активных стадиях (не закрытые)             // Можно раскомментировать для фильтра по ответственному
        ];


        // Шаг 1: Ищем ID дубликатов через специализированный метод
        $dupResult = $this->client->call('crm.duplicate.findbycomm', [
            'entity_type' => 'LEAD',
            'type'        => 'PHONE',
            'values'      => $phones
        ]);

        Log::debug('$dupResult');
        Log::debug($dupResult);

        // Проверяем, вернул ли метод совпадения по лидам
        if (empty($dupResult['result']['LEAD'])) {
            return false; // Дубликатов нет вообще
        }

        $leadIds = $dupResult['result']['LEAD'];

        // Шаг 2: Формируем запрос к crm.lead.list для применения дополнительных параметров
        $finalFilter = array_merge(
            ['=ID' => $leadIds], // Передаем ID найденных дублей
            $additionalFilter    // Накладываем ваши параметры (дата, стадия, ответственный и т.д.)
        );

        $leadsResult = $this->client->call('crm.lead.list', [
            'filter' => $finalFilter,
            'select' => ['ID', 'TITLE', 'STATUS_ID', 'DATE_CREATE', 'ASSIGNED_BY_ID'] // Нужные вам поля
        ]);

        Log::debug('$leadsResult');
        Log::debug($leadsResult);

        return ! empty($leadsResult['result']);
    }


    function findLeadDuplicatePhone(string $phone, ?string $email): array
    {
        $date = Carbon::now('Europe/Moscow')->subDay();
        $finalFilter = [
            '>DATE_CREATE'   => $date->toAtomString(), // За последние сутки
//            'STATUS_SEMANTIC_ID' => 'P',
            'SOURCE_ID' => 'WEB',
            'PHONE' => $phone,
//            'EMAIL' => $email,
        ];

        $leadsResult = $this->client->call('crm.lead.list', [
            'filter' => $finalFilter,
            'select' => ['ID'] // Нужные вам поля
        ]);

        Log::debug('$leadsResult');
        Log::debug($leadsResult);

        return $leadsResult['result'] ?? [];
    }

}
