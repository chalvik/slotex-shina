<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\CrmFeedback;

use App\Models\CrmFeedback;
use App\MoonShine\Resources\CrmFeedback\Pages\CrmFeedbackDetailPage;
use App\MoonShine\Resources\CrmFeedback\Pages\CrmFeedbackFormPage;
use App\MoonShine\Resources\CrmFeedback\Pages\CrmFeedbackIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;

/**
 * @extends ModelResource<CrmFeedback, CrmFeedbackIndexPage, CrmFeedbackFormPage, CrmFeedbackDetailPage>
 */
class CrmFeedbackResource extends ModelResource
{
    protected string $model = CrmFeedback::class;

    protected string $title = 'CrmFeedbacks';

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            CrmFeedbackIndexPage::class,
            CrmFeedbackFormPage::class,
            CrmFeedbackDetailPage::class,
        ];
    }
}
