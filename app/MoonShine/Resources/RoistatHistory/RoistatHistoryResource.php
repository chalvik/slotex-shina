<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\RoistatHistory;

use App\Models\RoistatHistory;
use App\MoonShine\Resources\RoistatHistory\Pages\RoistatHistoryDetailPage;
use App\MoonShine\Resources\RoistatHistory\Pages\RoistatHistoryFormPage;
use App\MoonShine\Resources\RoistatHistory\Pages\RoistatHistoryIndexPage;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Laravel\Resources\ModelResource;

/**
 * @extends ModelResource<RoistatHistory, RoistatHistoryIndexPage, RoistatHistoryFormPage, RoistatHistoryDetailPage>
 */
class RoistatHistoryResource extends ModelResource
{
    protected string $model = RoistatHistory::class;

    protected string $title = 'RoistatHistories';

    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            RoistatHistoryIndexPage::class,
            RoistatHistoryFormPage::class,
            RoistatHistoryDetailPage::class,
        ];
    }
}
