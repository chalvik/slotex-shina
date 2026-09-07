<?php

namespace App\Services\Handlers;

use Illuminate\Support\Facades\Log;

class RoistatNotificationHandler
{
    public function handle(array $data): bool
    {
        $event = $data['notification_event'] ?? 'unknown';

        Log::info('Roistat notification received', [
            'event' => $event,
            'project' => $data['project_name'] ?? 'unknown',
            'data' => $data,
        ]);

        return match ($event) {
            'proxy_lead_created' => $this->handleLeadCreated($data),
            'proxy_lead_not_sent' => $this->handleLeadNotSent($data),
            'got_proxy_lead_duplicate' => $this->handleLeadDuplicate($data),
            'call_not_answered' => $this->handleCallNotAnswered($data),
            'caught_lead' => $this->handleCaughtLead($data),
            'daily_statistics' => $this->handleDailyStatistics($data),
            'low_script_accuracy' => $this->handleLowAccuracy($data),
            'phones_will_be_removed' => $this->handlePhonesWillBeRemoved($data),
            'chat_with_low_rate' => $this->handleChatLowRate($data),
            default => $this->handleUnknownEvent($data),
        };
    }

    private function handleLeadCreated(array $data): bool
    {
        Log::info('✅ Lead successfully created in Roistat', ['lead_id' => $data['lead_id'] ?? 'unknown']);
        return true;
    }

    private function handleLeadNotSent(array $data): bool
    {
        Log::warning('⚠️ Roistat lead not sent to CRM', [
            'error' => $data['error'] ?? 'unknown',
        ]);
        return true;
    }

    private function handleLeadDuplicate(array $data): bool
    {
        Log::warning('⚠️ Roistat detected duplicate lead', [
            'lead_id' => $data['lead_id'] ?? 'unknown',
            'duplicate_lead_id' => $data['duplicate_lead_id'] ?? 'unknown',
        ]);
        return true;
    }

    private function handleCallNotAnswered(array $data): bool
    {
        Log::info('📱 Missed call in Roistat', [
            'caller' => $data['caller'] ?? 'unknown',
            'callee' => $data['callee'] ?? 'unknown',
        ]);
        return true;
    }

    private function handleCaughtLead(array $data): bool
    {
        Log::info('🎯 Roistat Lead Hunter caught a lead', [
            'name' => $data['name'] ?? 'unknown',
            'phone' => $data['phone'] ?? 'unknown',
        ]);
        return true;
    }

    private function handleDailyStatistics(array $data): bool
    {
        Log::info('📊 Roistat daily statistics', [
            'calls' => $data['calls'] ?? 0,
            'leads' => $data['leads'] ?? 0,
            'conversion' => $data['conversion'] ?? '0%',
        ]);
        return true;
    }

    private function handleLowAccuracy(array $data): bool
    {
        Log::warning('📉 Roistat call tracking accuracy low', [
            'accuracy' => $data['accuracy'] ?? 'unknown',
        ]);
        return true;
    }

    private function handlePhonesWillBeRemoved(array $data): bool
    {
        Log::critical('🚨 Roistat numbers will be removed', [
            'days_until' => $data['days_until'] ?? 'unknown',
        ]);
        return true;
    }

    private function handleChatLowRate(array $data): bool
    {
        Log::warning('💬 Chat closed with low rate', [
            'rate' => $data['rate'] ?? 'unknown',
            'manager' => $data['manager'] ?? 'unknown',
        ]);
        return true;
    }

    private function handleUnknownEvent(array $data): bool
    {
        Log::info('❓ Unknown Roistat notification event', [
            'event' => $data['notification_event'] ?? 'unknown',
        ]);
        return true;
    }
}