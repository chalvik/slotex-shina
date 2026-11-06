<?php

declare(strict_types=1);

namespace App\MoonShine\Resources\Test;

use Illuminate\Database\Eloquent\Model;
use App\Models\Test;
use App\MoonShine\Resources\Test\Pages\TestIndexPage;
use App\MoonShine\Resources\Test\Pages\TestFormPage;
use App\MoonShine\Resources\Test\Pages\TestDetailPage;

use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Contracts\Core\PageContract;

/**
 * @extends ModelResource<Test, TestIndexPage, TestFormPage, TestDetailPage>
 */
class TestResource extends ModelResource
{
    protected string $model = Test::class;

    protected string $title = 'Tests';
    
    /**
     * @return list<class-string<PageContract>>
     */
    protected function pages(): array
    {
        return [
            TestIndexPage::class,
            TestFormPage::class,
            TestDetailPage::class,
        ];
    }
}
