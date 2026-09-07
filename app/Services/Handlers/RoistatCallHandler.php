<?php

namespace App\Services\Handlers;

use App\Services\Bitrix24\LeadService;
use Illuminate\Support\Facades\Log;

class RoistatCallHandler
{
    private LeadService $leadService;

    public function __construct(LeadService $leadService)
    {
        $this->leadService = $leadService;
    }

    public function handle(array $data): bool
    {
        $status = $data['status'] ?? 'ACTIVE';

        // Обрабатываем только отвеченные звонки
        if (!in_array($status, ['ANSWER', 'ACTIVE'])) {
            Log::info('Call not answered, skipping', [
                'call_id' => $data['id'] ?? 'unknown',
                'status' => $status,
            ]);
            return true;
        }

        $phone = $data['caller'] ?? '';
        if (!$phone) {
            Log::warning('No caller phone', ['data' => $data]);
            return false;
        }

        // Проверяем существование лида
        /*$existingLead = $this->leadService->findByPhone($phone);
        if ($existingLead) {
            Log::info('Lead exists, updating', [
                'phone' => $phone,
                'lead_id' => $existingLead['ID'],
            ]);
            return $this->updateExistingLead($existingLead['ID'], $data);
        }*/
Log::info('Creating new lead for phone', ['phone' => $phone]);
        // Создаем лид
        $leadData = $this->prepareLeadData($data);
        $leadId = $this->leadService->create($leadData);

        if ($leadId) {
            Log::info('Lead created from call', [
                'lead_id' => $leadId,
                'call_id' => $data['id'] ?? 'unknown',
            ]);

            // Добавляем запись разговора, если есть
            if (!empty($data['link'])) {
                $this->addCallRecord($leadId, $data);
            }

            return true;
        }

        return false;
    }

    private function prepareLeadData(array $data): array
    {
        $phone = $data['caller'] ?? '';
        $name = $data['name'] ?? 'Звонок с лендинга';
        $title = sprintf('Входящий звонок: %s %s', $name, $phone);

        $leadData = [
            'TITLE' => $title,
            'NAME' => $name,
            'PHONE' => [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']],
            'STATUS_ID' => $this->getStatusId($data),
            'ASSIGNED_BY_ID' => config('services.bitrix24.default_responsible', 1),
            'SOURCE_ID' => 'CALL',
            'SOURCE_DESCRIPTION' => $this->buildSourceDescription($data),
            'COMMENTS' => $this->buildComments($data),
        ];

        // UTM-метки
        $utmFields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        foreach ($utmFields as $field) {
            if (!empty($data[$field])) {
                $leadData[strtoupper($field)] = $data[$field];
            }
        }

        // Пользовательские поля
        $leadData[config('services.bitrix24.visit_field', 'UF_CRM_ROISTAT_VISIT')] = $data['visit_id'] ?? '';
        $leadData[config('services.bitrix24.source_field', 'UF_CRM_SOURCE')] = $data['marker'] ?? 'Звонок';

        if (!empty($data['link'])) {
            $leadData[config('services.bitrix24.call_record_field', 'UF_CRM_CALL_RECORD')] = $data['link'];
        }

        return $leadData;
    }

    private function getStatusId(array $data): string
    {
        $marker = $data['marker'] ?? '';

        if (strpos($marker, 'fb') !== false || strpos($marker, 'facebook') !== false) {
            return 'FB_NEW';
        } elseif (strpos($marker, 'google') !== false) {
            return 'GOOGLE_NEW';
        } elseif (strpos($marker, 'yandex') !== false) {
            return 'YANDEX_NEW';
        }

        return config('services.bitrix24.default_status', 'NEW');
    }

    private function buildSourceDescription(array $data): string
    {
        return sprintf(
            'Звонок на номер: %s | Статус: %s | Длительность: %s сек | Город: %s',
            $data['callee'] ?? 'Неизвестно',
            $data['status'] ?? 'ACTIVE',
            $data['duration'] ?? 0,
            $data['city'] ?? 'Неизвестно'
        );
    }

private function buildComments(array $data): string
{
    $date = isset($data['date']) ? $data['date'] : date('Y-m-d H:i:s');
    $duration = isset($data['duration']) ? $data['duration'] : 0;
    $landingPage = isset($data['landing_page']) ? $data['landing_page'] : '—';
    $domain = isset($data['domain']) ? $data['domain'] : '—';
    $referrer = isset($data['referrer']) ? $data['referrer'] : '—';

    $comments = [
        "📞 Информация о звонке",
        "Дата: {$date}",
        "Длительность: {$duration} сек",
        "Страница: {$landingPage}",
        "Домен: {$domain}",
        "Referrer: {$referrer}",
    ];

    if (!empty($data['link'])) {
        $comments[] = "Запись: {$data['link']}";
    }

    return implode("\n", $comments);
}

    private function updateExistingLead(int $leadId, array $data): bool
    {
        $updateData = [
            'COMMENTS' => $this->buildComments($data),
        ];

        $utmFields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        foreach ($utmFields as $field) {
            if (!empty($data[$field])) {
                $updateData[strtoupper($field)] = $data[$field];
            }
        }

        if (!empty($data['link'])) {
            $updateData[config('services.bitrix24.call_record_field', 'UF_CRM_CALL_RECORD')] = $data['link'];
        }

        return $this->leadService->update($leadId, $updateData);
    }

    private function addCallRecord(int $leadId, array $data): void
    {
        $this->leadService->update($leadId, [
            config('services.bitrix24.call_record_field', 'UF_CRM_CALL_RECORD') => $data['link'],
            'COMMENTS' => "Запись разговора: {$data['link']}",
        ]);
    }
}