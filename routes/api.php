<?php

use App\Http\Controllers\Api\ProjectAlertRuleController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectNotificationController;
use App\Http\Controllers\Api\ProjectSubscriberController;
use App\Http\Controllers\Api\ProjectWebhookSourceController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/{projectUuid}/{sourceKey}', [WebhookController::class, 'store']);

Route::middleware('tenant')->group(function (): void {
    Route::get('/tenant', function () {
        return response()->json([
            'organization' => request()->attributes->get('organization'),
        ]);
    });

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);

    Route::get('/projects/{project}/subscribers', [ProjectSubscriberController::class, 'index']);
    Route::post('/projects/{project}/subscribers', [ProjectSubscriberController::class, 'store']);

    Route::get('/projects/{project}/notifications', [ProjectNotificationController::class, 'index']);

    Route::get('/projects/{project}/alert-rules', [ProjectAlertRuleController::class, 'index']);
    Route::post('/projects/{project}/alert-rules', [ProjectAlertRuleController::class, 'store']);

    Route::post('/projects/{project}/webhook-sources', [ProjectWebhookSourceController::class, 'store']);
});
