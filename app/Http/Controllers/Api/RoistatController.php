<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Bitrix24\LeadService;
use App\Services\Bitrix24\DealService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class RoistatController extends Controller
{
    private LeadService $leadService;
    private DealService $dealService;

    public function __construct(LeadService $leadService, DealService $dealService)
    {
        $this->leadService = $leadService;
        $this->dealService = $dealService;
    }

    public function handleWebhook(Request $request)
    {
        /* $token = $request->header('X-Auth-Token');
        if ($token !== config('services.roistat.webhook_secret')) {
            Log::warning('Unauthorized webhook', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }*/

        $data = $request->all();
        Log::info('🔍 Roistat webhook received', ['data' => $data]);

        try {
            if (isset($data['caller']) || isset($data['callee'])) {
                return $this->handleCall($data);
            }

            if (isset($data['email']) || isset($data['name']) || isset($data['phone'])) {
                return $this->handleForm($data);
            }

            Log::warning('Unknown event type', ['data' => $data]);
            return response()->json(['status' => 'error', 'message' => 'Unknown event type'], 200);
        } catch (\Exception $e) {
            Log::error('❌ Ошибка обработки вебхука', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data,
            ]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 200);
        }
    }

    // ============================================================
    // ЗВОНКИ (с сессией)
    // ============================================================

    private function handleCall(array $data)
    {

    $callId = $data['id'] ?? null;
        if (!$callId) {
            Log::warning('Call without ID', ['data' => $data]);
            return response()->json(['status' => 'error', 'message' => 'Call ID required'], 200);
        }

        $status = $data['status'] ?? 'ACTIVE';
        $isMissed = in_array($status, ['NOANSWER', 'BUSY', 'CANCEL', 'CONGESTION', 'CHANUNAVAIL', 'DONTCALL', 'TORTURE']);
        $isDone = in_array($status, ['ANSWER']);

        // ✅ Проверяем, есть ли уже данные в сессии
        $sessionKey = "roistat_call_{$callId}";
        $stored = Session::get($sessionKey);

        if (!$stored) {
            // 🆕 ПЕРВЫЙ ЗАПРОС (во время звонка)
            Log::info('📞 Первый запрос (во время звонка)', [
                'call_id' => $callId,
                'status' => $status,
                'is_missed' => $isMissed,
            ]);

            // Для неотвеченных сделка не создается
         /*   file_put_content(
                'test.log', json_encode($data)
            );*/
            $dealId = null;
            if ($isMissed || $isDone) {

                // Создаем лид с учетом статуса
                $fields = $this->prepareLeadData($data, $isMissed);
                $leadId = $this->leadService->create($fields);

                if (!$leadId) {
                    Log::error('❌ Не удалось создать лид');
                    return response()->json(['status' => 'error', 'message' => 'Failed to create lead'], 200);
                }

                $dealId = $this->createDealFromLead($leadId, $fields, $isMissed);
            }

            // ✅ Сохраняем в сессию
            Session::put($sessionKey, [
                'lead_id' => $leadId,
                'deal_id' => $dealId,
                'status' => $status,
                'is_missed' => $isMissed,
                'created_at' => now()->toDateTimeString(),
            ]);

            Log::info('✅ Данные сохранены в сессию', [
                'call_id' => $callId,
                'lead_id' => $leadId,
                'deal_id' => $dealId,
            ]);

            return response()->json([
                'status' => 'success',
                'lead_id' => $leadId,
                'deal_id' => $dealId,
                'message' => $isMissed ? 'Missed call, lead created' : 'Lead and deal created',
            ]);
        }

        // 🔄 ВТОРОЙ ЗАПРОС (после звонка)
        Log::info('📞 Второй запрос (после звонка)', [
            'call_id' => $callId,
            'stored' => $stored,
        ]);

        $leadId = $stored['lead_id'];
        $dealId = $stored['deal_id'] ?? null;

        // Добавляем запись разговора
        if (!empty($data['link'])) {
            $this->leadService->update($leadId, [
                'UF_CRM_1786433201' => $data['link'],
                'COMMENTS' => "🔗 Запись разговора: " . $data['link'],
            ]);

            if ($dealId) {
                $this->dealService->update($dealId, [
                    'UF_CRM_1786434399' => $data['link'],
                    'COMMENTS' => "🔗 Запись разговора: " . $data['link'],
                ]);
            }

            Log::info('✅ Запись разговора добавлена', ['lead_id' => $leadId, 'deal_id' => $dealId]);
        }

        // Обновляем статус
        if (!empty($data['status'])) {
            $comments = "Статус звонка: {$data['status']} | Длительность: " . ($data['duration'] ?? 0) . " сек";
            $this->leadService->update($leadId, ['COMMENTS' => $comments]);
        }

        // ✅ Удаляем из сессии (можно оставить для истории)
        // Session::forget($sessionKey);

        return response()->json([
            'status' => 'success',
            'lead_id' => $leadId,
            'deal_id' => $dealId,
            'message' => 'Call record added',
        ]);
    }

    // ============================================================
    // ФОРМЫ
    // ============================================================

    private function handleForm(array $data)
    {
        Log::info('📝 Обработка формы', [
            'visit_id' => $data['visit_id'] ?? 'unknown',
            'name' => $data['name'] ?? 'unknown',
            'has_data' => isset($data['data']) ? 'yes' : 'no',
        ]);

        // Парсим JSON из поля 'data'
        $parsedData = [];
        if (isset($data['data']) && is_string($data['data'])) {
            $parsedData = json_decode($data['data'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('⚠️ Ошибка парсинга data', ['data' => $data['data']]);
                $parsedData = [];
            } else {
                Log::info('✅ Распарсенные данные из data', ['parsed' => $parsedData]);
            }
        }


        $leads = $this->leadService->findLeadDuplicatePhone(
            phone: $data['phone']?? '',
            email: $data['email']?? '',
        );


        Log::debug($leads);
        if (count($leads) < 1)
         //  Проверить лид на дублирование
        {
            $mergedData = array_merge($data, $parsedData);
            $mergedData['custom_fields'] = $parsedData;
            $leadId = $this->createLeadFromForm($mergedData);

            if (!$leadId) {
                Log::error('❌ Не удалось создать лид из формы');
                return response()->json(['status' => 'error', 'message' => 'Failed to create lead'], 200);
            }

            $dealId = $this->createDealFromLeadForm($leadId, $mergedData);
        } else {
            $lead = array_first($leads);
            $leadId = $lead['ID'];
            return response()->json([
                'status' => 'success',
                'lead_id' => $leadId ,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'lead_id' => $leadId ,
            'deal_id' => $dealId ,
            'order_id' => $dealId ,
        ]);
    }

    private function createLeadFromForm(array $data): ?int
    {
        $phone = $data['phone'] ?? '';
        $name = $data['name'] ?? 'Клиент';
        $email = $data['email'] ?? '';
        $comment = $data['text'] ?? $data['comment'] ?? '';
        $title = $data['title'] ?? 'Заявка с лендинга';

        $city = $data['UF_CRM_1786432965'] ?? '';
        $dealer = $data['UF_CRM_1786433087'] ?? '';
        $product = $data['UF_CRM_1786433126'] ?? '';
        $visitId = $data['UF_CRM_1785827518'] ?? '';
        $request = $data['UF_CRM_1786433238'] ?? '';
        $commentFromField = $data['UF_CRM_1786433301'] ?? '';

        if (empty($comment) && !empty($commentFromField)) {
            $comment = $commentFromField;
        }

        if (empty($city) && isset($data['landing_page'])) {
            $landingPage = $data['landing_page'] ?? '';
            if (empty($product)) {
                $product = $this->getProductFromLanding($landingPage);
            }
        }

        if (empty($city)) {
            $city = $data['DEALER_CITY'] ?? '';
            $dealer = $data['DEALER_NAME'] ?? '';
        }

        Log::info('📤 Создание лида из формы', [
            'city' => $city,
            'dealer' => $dealer,
            'product' => $product,
            'visit_id' => $visitId,
        ]);

        $fields = [
            'TITLE' => $title . ': ' . $name . ' ' . $phone,
            'NAME' => $name,
            'PHONE' => $phone ? [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']] : [],
            'EMAIL' => $email ? [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']] : [],
            'STATUS_ID' => 'UC_D7Z355',
            'ASSIGNED_BY_ID' => $this->getResponsibleId($city),
            'SOURCE_ID' => 'WEB',
            'SOURCE_DESCRIPTION' => "Заявка с лендинга | Город: " . ($city ?: 'Не указан') . " | Дилер: " . ($dealer ?: 'Не указан'),
            'COMMENTS' => "📝 Информация о заявке\n" .
                           "Дата: " . ($data['date'] ?? date('Y-m-d H:i:s')) . "\n" .
                           "Город: " . ($city ?: 'Не указан') . "\n" .
                           "Дилер: " . ($dealer ?: 'Не указан') . "\n" .
                           "Продукт: " . ($product ?: 'unknown') . "\n" .
                           "Комментарий: " . ($comment ?: '—'),
            'UF_CRM_1786432965' => $city,
            'UF_CRM_1786433087' => $dealer,
            'UF_CRM_1786433126' => $product,
            'UF_CRM_1785827518' => $visitId,
            'UF_CRM_1786433301' => $comment,
            'UF_CRM_1786433238' => $request,
        ];

        $utmFields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        foreach ($utmFields as $field) {
            if (!empty($data[$field])) {
                $fields[strtoupper($field)] = $data[$field];
            }
        }

        return $this->leadService->create($fields);
    }

    // ============================================================
    // ОБЩИЕ МЕТОДЫ
    // ============================================================

    private function getProductFromLanding(string $landingPage): string
    {
        if (empty($landingPage)) {
            return 'unknown';
        }

        $products = ['rau', 'duco', 'hpl'];
        foreach ($products as $product) {
            if (stripos($landingPage, '/' . $product) !== false) {
                return $product;
            }
        }

        parse_str(parse_url($landingPage, PHP_URL_QUERY) ?? '', $query);
        if (!empty($query['product'])) {
            return strtolower($query['product']);
        }

        return 'unknown';
    }

    private function prepareLeadData(array $data, bool $isMissed = false): array
    {
        $custom = $data['custom_fields'] ?? [];

        $phone = $data['caller'] ?? $data['phone'] ?? '';
        $name = $data['name'] ?? 'Клиент';
        $email = $data['email'] ?? '';

        $city = $data['city'] ?? $custom['city'] ?? '';
        $dealer = $data['dealer'] ?? $custom['dealer'] ?? '';
        $landingPage = $data['landing_page'] ?? $custom['landing_page'] ?? '';
        $visitId = $data['visit_id'] ?? $custom['visit_id'] ?? '';

        if (empty($city) && isset($data['callee'])) {
            $dealerInfo = $this->getDealerByCallee($data['callee']);
            $city = $dealerInfo['city'] ?? '';
            $dealer = $dealerInfo['dealer'] ?? '';
            $product = $dealerInfo['product'] ?? '';
        }

        $product = $data['product'] ?? $custom['product'] ?? '';
        if (empty($product)) {
            $product = $this->getProductFromLanding($landingPage);
        }

        $recordLink = $data['link'] ?? '';
        $comment = $data['comment'] ?? '';
        $request = $data['request'] ?? '';

        $isCall = isset($data['caller']) || isset($data['callee']);

        // ✅ Заголовок в зависимости от статуса
        if ($isMissed) {
            $title = sprintf('📞 Неотвеченный звонок: %s %s', $name, $phone);
            $sourceDesc = "Пропущенный звонок на номер: " . ($data['callee'] ?? 'неизвестно');
            $statusId = 'UC_D7Z355';
            $comments = "📞 Пропущенный звонок\nДата: " . ($data['date'] ?? date('Y-m-d H:i:s'));
        } else {
            $title = $isCall
                ? sprintf('Входящий звонок: %s %s', $name, $phone)
                : sprintf('Заявка с лендинга: %s %s', $name, $phone);
            $sourceDesc = $isCall
                ? "Звонок на номер: " . ($data['callee'] ?? 'неизвестно') . " | Статус: " . ($data['status'] ?? 'ANSWER')
                : "Заявка с лендинга | Город: " . ($city ?: 'Не указан') . " | Дилер: " . ($dealer ?: 'Не указан');
            $statusId = 'UC_D7Z355';
            $comments = $this->buildComments($data);
        }

        $fields = [
            'TITLE' => $title,
            'NAME' => $name,
            'PHONE' => $phone ? [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']] : [],
            'EMAIL' => $email ? [['VALUE' => $email, 'VALUE_TYPE' => 'WORK']] : [],
            'STATUS_ID' => $statusId,
            'ASSIGNED_BY_ID' => $this->getResponsibleId($city),
            'SOURCE_ID' => $isCall ? 'CALL' : 'WEB',
            'SOURCE_DESCRIPTION' => $sourceDesc,
            'COMMENTS' => $comments,
            'UF_CRM_1786432965' => $city,
            'UF_CRM_1786433087' => $dealer,
            'UF_CRM_1786433126' => $product,
            'UF_CRM_1785827518' => $visitId,
            'UF_CRM_1786433201' => $recordLink,
            'UF_CRM_1786433301' => $comment,
            'UF_CRM_1786433238' => $request,
        ];

        $utmFields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        foreach ($utmFields as $field) {
            if (!empty($data[$field])) {
                $fields[strtoupper($field)] = $data[$field];
            }
        }

        return $fields;
    }



    // C6:UC_S9LHTU - заявка с сайта
    //C6:UC_41XOVC -  принятый звонок
    private function createDealFromLead(int $leadId, array $data, bool $isMissed = false): ?int
    {
        Log::debug('createDealFromLead');
        Log::debug($data);
        Log::debug($leadId);
        Log::debug($isMissed);
        $city = $data['UF_CRM_1786432965'] ?? '';
        $dealer = $data['UF_CRM_1786433087'] ?? '';
        $product = $data['UF_CRM_1786433126'] ?? '';
        $visitId = $data['UF_CRM_1785827518'] ?? '';
        $comment = $data['COMMENTS'] ?? '';
        $request = $data['UF_CRM_1786433238'] ?? '';
        $phone = $data['PHONE'][0]['VALUE'] ?? '';
        $name = $data['NAME'] ?? 'Клиент';
        $title = $data['TITLE'] ?? sprintf('Сделка: %s %s', $name, $phone);

//        if (empty($product) && isset($data['landing_page'])) {
//            $landingPage = $data['landing_page'] ?? '';
//            $product = $this->getProductFromLanding($landingPage);
//        }

//        if (empty($city)) {
//            $city = $data['DEALER_CITY'] ?? '';
//            $dealer = $data['DEALER_NAME'] ?? '';
//        }

        $fields = [
            'TITLE' => $title,
            'ASSIGNED_BY_ID' => $this->getResponsibleId($city),
            'CATEGORY_ID' => 6,
            'STAGE_ID' =>  $isMissed ? 'C6:NEW' : 'C6:UC_41XOVC',
            'SOURCE_ID' => 'CALL',
            'LEAD_ID' => $leadId,
//            'PHONE' => $phone,
            'COMMENTS' => "Создана из лида #{$leadId}\nГород: " . ($city ?: 'Не указан') . "\nДилер: " . ($dealer ?: 'Не указан') . "\nПродукт: " . ($product ?: 'unknown'),
            'UF_CRM_1786434253' => $city,
            'UF_CRM_1786434296' => $dealer,
            'UF_CRM_1786434320' => $product,
            'UF_CRM_1785827564' => $visitId,
            'UF_CRM_1786434413' => $comment,
            'UF_CRM_1786434431' => $request,
        ];

        return $this->dealService->create($fields);
    }


    private function createDealFromLeadForm(int $leadId, array $data): ?int
    {
        Log::debug('createDealFromLeadForm');
        Log::debug($data);
        Log::debug($leadId);
        $city = $data['UF_CRM_1786432965'] ?? '';
        $dealer = $data['UF_CRM_1786433087'] ?? '';
        $product = $data['UF_CRM_1786433126'] ?? '';
        $visitId = $data['UF_CRM_1785827518'] ?? '';
        $comment = $data['text'] ?? $data['comment'] ?? '';
        $request = $data['UF_CRM_1786433238'] ?? '';
        $phone = $data['phone'] ?? '';
        $name = $data['name'] ?? 'Клиент';

        if (empty($product) && isset($data['landing_page'])) {
            $landingPage = $data['landing_page'] ?? '';
            $product = $this->getProductFromLanding($landingPage);
        }

        if (empty($city)) {
            $city = $data['DEALER_CITY'] ?? '';
            $dealer = $data['DEALER_NAME'] ?? '';
        }

        $fields = [
            'TITLE' => sprintf('Сделка: %s %s', $name, $phone),
            'ASSIGNED_BY_ID' => $this->getResponsibleId($city),
            'CATEGORY_ID' => 6,
            'STAGE_ID' => 'C6:NEW',
            'LEAD_ID' => $leadId,
            'COMMENTS' => "Создана из лида #{$leadId}\nГород: " . ($city ?: 'Не указан') . "\nДилер: " . ($dealer ?: 'Не указан') . "\nПродукт: " . ($product ?: 'unknown'),
            'UF_CRM_1786434253' => $city,
            'UF_CRM_1786434296' => $dealer,
            'UF_CRM_1786434320' => $product,
            'UF_CRM_1785827564' => $visitId,
            'UF_CRM_1786434413' => $comment,
            'UF_CRM_1786434431' => $request,
        ];

        if ($phone) {
            $fields['PHONE'] = [['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']];
        }

        return $this->dealService->create($fields);
    }


    private function getDealerByCallee(string $callee): array
    {
        $map = [
            // '+74951234567' => ['city' => 'Казань', 'dealer' => 'Строительный двор', 'product' => 'duco'],
        ];
        return $map[$callee] ?? [];
    }

    private function buildComments(array $data): string
    {
        $comments = [];
        if (isset($data['caller'])) {
            $comments[] = "📞 Информация о звонке";
            $comments[] = "Дата: " . ($data['date'] ?? date('Y-m-d H:i:s'));
            $comments[] = "Длительность: " . ($data['duration'] ?? 0) . " сек";
            if (!empty($data['link'])) {
                $comments[] = "🔗 Запись разговора: " . $data['link'];
            }
        } else {
            $comments[] = "📝 Информация о заявке";
            $comments[] = "Дата: " . ($data['date'] ?? date('Y-m-d H:i:s'));
            if (!empty($data['comment'])) {
                $comments[] = "Комментарий: " . $data['comment'];
            }
            if (!empty($data['request'])) {
                $comments[] = "Запрос: " . $data['request'];
            }
        }
        return implode("\n", $comments);
    }

    private function getResponsibleId(string $city): int
    {
        $map = [
            'Казань' => 403,
            'Нижний Новгород' => 403,
            'Самара' => 403,
            'Уфа' => 403,
            'Набережные Челны' => 403,
            'Архангельск' => 403,
            'Рязань' => 367,
            'Курск' => 367,
            'Владимир' => 367,
            'Волгоград' => 367,
            'Краснодар' => 367,
            'Екатеринбург' => 366,
            'Красноярск' => 366,
        ];

        return $map[$city] ?? 1410;
    }
}
