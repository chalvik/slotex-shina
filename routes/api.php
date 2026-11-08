<?php

use App\Http\Controllers\Api\RoistatController;
use App\Http\Controllers\Api\RoistatExportController;
use App\Http\Controllers\Api\TestRoistatController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\CrmFeedbackController;

Route::prefix('roistat')->group(function () {
    // Прием вебхуков от Roistat (звонки, формы)
    Route::post('/webhook', [RoistatController::class, 'handleWebhook']);

    // Выгрузка данных по запросу Roistat (для "Своя CRM")
    Route::get('/export', [RoistatExportController::class, 'handleExport']);
    Route::get('/test', [RoistatExportController::class, 'handleExport2']);
    Route::get('/test2', [TestRoistatController::class, 'test']);

});

Route::prefix('v1')->group(function () {
// Crm  Feedback
    Route::post('/crm/feedback', [CrmFeedbackController::class, 'store']);
});
