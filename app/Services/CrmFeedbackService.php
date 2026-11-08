<?php

namespace App\Services;

use App\Dto\DtoCrmFeedback;
use App\Models\CrmFeedback;

class CrmFeedbackService
{
    public function store(DtoCrmFeedback $dto): CrmFeedback
    {
        return CrmFeedback::query()->create([
            'name' => $dto->name,
            'city' => $dto->city,
            'email' => $dto->email,
            'phone' => $dto->phone,
            'content' => $dto->content,
            'image' => null, // $dto->image,
            'prompt' => $dto->prompt,
            'source' => $dto->source,
        ]);
    }
}
