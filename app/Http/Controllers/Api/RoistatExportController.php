<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Bitrix24\DealService;
use App\Services\Bitrix24\LeadService;
use App\Services\Bitrix24\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RoistatExportController extends Controller
{
    private DealService $dealService;
    private LeadService $leadService;
    private UserService $userService;

    public function __construct(
        DealService $dealService,
        LeadService $leadService,
        UserService $userService
    ) {
        $this->dealService = $dealService;
        $this->leadService = $leadService;
        $this->userService = $userService;
    }

    public function handleExport(Request $request)
    {
      /*  if (!$this->authenticate($request)) {
            Log::warning('Unauthorized export request', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }*/

        $action = $request->input('action');
        $date = (int)$request->input('date', time());
        $offset = (int)$request->input('offset', 0);
        $limit = (int)$request->input('limit', 10000);

        Log::info('📤 Roistat export request', [
            'action' => $action,
            'date' => $date,
            'offset' => $offset,
            'limit' => $limit,
        ]);

        try {
            $response = match ($action) {
                'import_scheme' => $this->handleImportScheme(),
                'export' => $this->handleExportOrders($date, $offset, $limit),
                'export_clients' => $this->handleExportClients($date, $offset, $limit),
                'export_products' => $this->handleExportProducts($date, $offset, $limit),
                default => ['error' => "Unknown action: {$action}"]
            };

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('❌ Export error', ['action' => $action, 'error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function authenticate(Request $request): bool
    {
        $user = $request->input('user');
        $token = $request->input('token');

        if (!$user || !$token) {
            return false;
        }

        $expectedToken = md5(
            config('services.roistat.export_user') .
            config('services.roistat.export_password')
        );

        return $token === $expectedToken;
    }

    private function handleImportScheme(): array
    {
        Log::info('📋 Import scheme requested1');

        return [
            'statuses' => $this->getStatuses(),
            'fields' => $this->getFields(),
            //'managers' => $this->getManagers(),
        ];
    }

    private function getClientsFromBitrix24(int $date, int $offset, int $limit): array
{
    $filter = [];
    if ($date) {
        $filter['>DATE_MODIFY'] = date('Y-m-d H:i:s', $date);
    }

    // ✅ Используем новый вебхук crm.contact.list
    $response = $this->client->call('crm.contact.list', [
        'filter' => $filter,
        'select' => ['ID', 'NAME', 'LAST_NAME', 'PHONE', 'EMAIL', 'COMPANY_TITLE'],
        'order' => ['DATE_CREATE' => 'DESC'],
        'start' => $offset,
    ]);

    if (isset($response['error']) || empty($response['result'])) {
        Log::warning('No contacts found', ['response' => $response]);
        return [];
    }

    $clients = [];
    foreach ($response['result'] as $contact) {
        $clients[] = [
            'id' => $contact['ID'],
            'name' => trim(($contact['NAME'] ?? '') . ' ' . ($contact['LAST_NAME'] ?? '')),
            'phone' => $contact['PHONE'][0]['VALUE'] ?? '',
            'email' => $contact['EMAIL'][0]['VALUE'] ?? '',
            'company' => $contact['COMPANY_TITLE'] ?? '',
        ];
    }

    return $clients;
}

    private function handleExportOrders(int $date, int $offset, int $limit): array
    {
        $filter = [];
        if ($date) {
            $filter['>DATE_MODIFY'] = date('Y-m-d H:i:s', $date);
        }

        $deals = $this->dealService->list($filter, $offset, $limit);
        //print_r('<pre>1'.$deals.'</pre>');

        $total = $this->getTotalOrdersCount($date);

        $orders = [];
        foreach ($deals as $deal) {
            $orders[] = [
                'id' => $deal['ID'],
                'name' => $deal['TITLE'] ?? 'Сделка #' . $deal['ID'],
                'date_create' => strtotime($deal['DATE_CREATE']),
                'status' => $deal['STAGE_ID'] ?? 'NEW',
                'price' => (float)($deal['OPPORTUNITY'] ?? 0),
                'roistat' => $deal['UF_CRM_1785827564'] ?? '',
                'client_id' => $deal['CONTACT_ID'] ?? '',
                'manager_id' => $deal['ASSIGNED_BY_ID'] ?? '',
                'fields' => $this->extractUserFields($deal),
                'products' => [],
            ];
        }

        return [
            'orders' => $orders,
            'statusess' => $this->getStatuses(),
            'fields' => $this->getFields(),
        ///'managers' => $this->getManagers(),
            'pagination' => [
                'total_count' => $total,
                'limit' => $limit,
            ],
        ];
    }

    private function handleExportClients(int $date, int $offset, int $limit): array
    {
        return [
            'clients' => [],
            'pagination' => [
                'total_count' => 0,
                'limit' => $limit,
            ],
        ];
    }

    private function handleExportProducts(int $date, int $offset, int $limit): array
    {
        return [
            'products' => [],
            'pagination' => [
                'total_count' => 0,
                'limit' => $limit,
            ],
        ];
    }

    private function getStatuses(): array
    {
        $statuses = $this->dealService->getStatuses();
        $result = [];

        foreach ($statuses as $status) {
            $result[] = [
                'id' => $status['STATUS_ID'] ?? $status['ID'],
                'name' => $status['NAME'] ?? $status['ID'],
            ];
        }

        return $result;
    }

    private function getFields(): array
    {
        $fields = $this->dealService->getFields();
        $result = [];

        foreach ($fields as $key => $field) {
        // Убираем фильтр UF_CRM — включаем ВСЕ поля
        $result[] = [
            'id' => $key,
            'name' => $field['title'] ?? $key,
        ];
    }

        return $result;
    }

    private function getManagers(): array
    {
        $users = $this->userService->getAll();
        $result = [];

        foreach ($users as $user) {
            $result[] = [
                'id' => $user['ID'],
                'name' => trim(($user['NAME'] ?? '') . ' ' . ($user['LAST_NAME'] ?? '')),
                'phone' => $user['PERSONAL_PHONE'] ?? '',
                'email' => $user['EMAIL'] ?? '',
            ];
        }

        return $result;
    }

    private function extractUserFields(array $deal): array
    {
        $fields = [];
        $prefixes = ['UF_CRM_'];

        foreach ($deal as $key => $value) {
            foreach ($prefixes as $prefix) {
                if (strpos($key, $prefix) === 0 && !empty($value)) {
                    $fields[$key] = $value;
                }
            }
        }

        return $fields;
    }

    private function getTotalOrdersCount(int $date): int
    {
        return 0;
    }

    public function handleExport2(){
        echo 'test';
    }
}