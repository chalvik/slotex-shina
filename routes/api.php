<?php
use App\Http\Controllers\Api\RoistatController;
use App\Http\Controllers\Api\RoistatExportController;
use Illuminate\Support\Facades\Route;

Route::prefix('roistat')->group(function () {
    //Прием вебхуков от Roistat (звонки, формы)
    Route::post('/webhook', [RoistatController::class, 'handleWebhook']);

    //Выгрузка данных по запросу Roistat (для "Своя CRM")
    Route::get('/export', [RoistatExportController::class, 'handleExport']);
    Route::get('/test', [RoistatExportController::class, 'handleExport2']);
    Route::get('/test2', [\App\Http\Controllers\Api\TestRoistatController::class, 'test']);

});
