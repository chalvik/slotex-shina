<?php

namespace App\Services;

use App\Dto\DtoFeedback;
use App\Models\CrmFeedback;

class CrmFeebbackService
{
    public function store(DtoFeedback $dto)
    {
        return CrmFeedback::query()->create([
            'name' => $dto->name,
            'city' => $dto->city,
            'email' => $dto->email,
            'phone' => $dto->phone,
            'content' => $dto->content,
            'image' => $dto->image,
            'prompt' => $dto->prompt,
            'source' => $dto->source,
        ]);
    }
}
