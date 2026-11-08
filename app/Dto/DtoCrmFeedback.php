<?php

namespace App\Dto;

class DtoCrmFeedback
{
    public function __construct(
        public string $name,
        public string $city,
        public ?string $email,
        public ?string $phone,
        public ?string $content,
        public ?string $image,
        public ?string $prompt,
        public ?int $source
    ) {}

}
