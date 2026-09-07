<?php
namespace App\Services\Bitrix24;
use Illuminate\Support\Facades\Log;

class UserService
{
    private Bitrix24Service $client;

    public function __construct(Bitrix24Service $client)
    {
        $this->client = $client;
    }

    public function getAll(): array
    {
        $allUsers = [];
        $start = 0;
        $limit = 50; // Максимум 50 за один запрос

        do {
            $response = $this->client->call('user.get', [
                'start' => $start,
            ]);

            if (isset($response['error'])) {
                Log::error('❌ Failed to get users', [
                    'error' => $response['error'],
                    'error_description' => $response['error_description'] ?? '',
                ]);
                break;
            }

            $users = $response['result'] ?? [];
            $allUsers = array_merge($allUsers, $users);

            // Проверяем, есть ли следующая страница
            $next = $response['next'] ?? null;
            if ($next === null || $next <= $start) {
                break;
            }
            $start = $next;

        } while (true);

        Log::info('✅ Total users loaded', ['count' => count($allUsers)]);
        return $allUsers;
    }


    public function getById(int $userId): ?array
    {
        $response = $this->client->call('user.get', ['ID' => $userId]);
        return $response['result'][0] ?? null;
    }


    
}