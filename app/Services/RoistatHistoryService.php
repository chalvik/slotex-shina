<?php

namespace App\Services;

use App\Models\RoistatHistory;

class RoistatHistoryService
{
    public function store(string $data): RoistatHistory
    {
        return RoistatHistory::query()->create([
            'data' => $data,
        ]);
    }
}
