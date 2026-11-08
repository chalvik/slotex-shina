<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCrmFeedbackRequest;
use App\Http\Resources\CrmFeedbackResource;
use App\Services\CrmFeedbackService;

class CrmFeedbackController extends Controller
{
    public function __construct(
        public CrmFeedbackService $feedbackService
    ) {}

    public function store(StoreCrmFeedbackRequest $request)
    {
        return CrmFeedbackResource::make($this->feedbackService->store($request->dto()));
    }
}
