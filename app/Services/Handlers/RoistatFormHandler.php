<?php

namespace App\Services\Handlers;

use App\Services\Bitrix24\LeadService;
use Illuminate\Support\Facades\Log;

class RoistatFormHandler
{
    private LeadService $leadService;

    public function __construct(LeadService $leadService)
    {
        $this->leadService = $leadService;
    }

    public function handle(array $data): bool
    {
        $phone = $data['phone'] ?? '';
        $email = $data['email'] ?? '';

        if (!$phone && !$email) {
            Log::warning('No phone or email', ['data' => $data]);
            return false;
        }

        if ($phone) {
            $existingLead = $this->leadService->findByPhone($phone);
            if ($existingLead) {
                Log::info('Lead exists, updating', [
                    'phone' => $phone,
                    'lead_id' => $existingLead['ID'],
                ]);
                return $this->updateExistingLead($existingLead['ID'], $data);
            }
        }

        $leadData = $this->prepareLeadData($data);
        $leadId = $this->leadService->create($leadData);

        if ($leadId) {
            Log::info('Lead created from form', [
                'lead_id' => $leadId,
                'visit_id' => $data['visit_id'] ?? 'unknown',
            ]);
            return true;
        }

        return false;
    }

    private function prepareLeadData(array $data): array
    {
        $name = $data['name'] ?? 'Без имени';
        $phone = $data['phone'] ?? '';
        $email = $data['email'] ?? '';
        $title = sprintf('Заявка с лендинга: %s %s', $name, $phone);

        $leadData = [
            'TITLE' => $title,
            'NAME' => $name,
            'PHONE' => $phone ? [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']] : [],
            'EMAIL' => $email ? [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']] : [],
            'STATUS_ID' => $this->getStatusId($data),
            'ASSIGNED_BY_ID' => config('services.bitrix24.default_responsible', 1),
            'SOURCE_ID' => 'WEB',
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
        $leadData[config('services.bitrix24.source_field', 'UF_CRM_SOURCE')] = $data['marker'] ?? 'Форма';

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
        } elseif (strpos($marker, 'organic') !== false) {
            return 'ORGANIC_NEW';
        }

        return config('services.bitrix24.default_status', 'NEW');
    }

    private function buildSourceDescription(array $data): string
    {
        $marker = $data['marker'] ?? 'Неизвестен';
        $parts = [
            '📝 Заявка с лендинга',
            "Источник:".$marker,
        ];

        if (!empty($data['domain'])) {
            $parts[] = "Домен: {$data['domain']}";
        }
        if (!empty($data['landing_page'])) {
            $parts[] = "Страница: {$data['landing_page']}";
        }
        if (!empty($data['city'])) {
            $parts[] = "Город: {$data['city']}";
        }

        return implode(' | ', $parts);
    }

    private function buildComments(array $data): string
    {
        $comments = [
            "📋 Информация о заявке",
            "Дата:". $data['date'] ?? date('Y-m-d H:i:s'),
            "IP:". $data['ip'] ?? '—',
            "Страна:". $data['country'] ?? '—',
            "Город:". $data['city'] ?? '—',
            "Страница:". $data['landing_page'] ?? '—',
        ];

        if (!empty($data['comment'])) {
            $comments[] = "Комментарий:". $data['comment'];
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

        $updateData[config('services.bitrix24.visit_field', 'UF_CRM_ROISTAT_VISIT')] = $data['visit_id'] ?? '';

        return $this->leadService->update($leadId, $updateData);
    }
}
