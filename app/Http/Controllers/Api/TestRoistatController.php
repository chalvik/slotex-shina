<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessRoistatCallJob;
use App\Jobs\ProcessRoistatFormJob;
use App\Jobs\ProcessRoistatNotificationJob;
use Illuminate\Http\Request;

class TestRoistatController extends Controller
{
    /**
     * Эмуляция звонка (во время соединения)
     */
    public function simulateCallStart(Request $request)
    {
        $data = [
            'id' => '313',
            'caller' => '+79123456789',
            'callee' => '+74951234567',
            'visit_id' => '123456',
            'marker' => 'fb_new',
            'order_id' => 123456789,
            'date' => date('Y-m-d H:i:s'),
            'landing_page' => 'test.com/new',
            'domain' => 'test.com',
            'city' => 'Москва',
            'country' => 'Россия',
            'ip' => '161.185.160.93',
            'referrer' => 'fb.com',
            'utm_source' => 'facebook',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'summer_sale',
            'utm_term' => 'купить диван',
            'utm_content' => 'banner_1',
            'status' => 'ACTIVE',
        ];

        ProcessRoistatCallJob::dispatch($data)->onQueue('roistat_calls');

        return response()->json([
            'status' => 'test_success',
            'type' => 'call_start',
            'data' => $data,
        ]);
    }

    /**
     * Эмуляция звонка (после завершения с записью)
     */
    public function simulateCallEnd(Request $request)
    {
        $data = [
            'id' => '313',
            'caller' => '+79123456789',
            'callee' => '+74951234567',
            'visit_id' => '123456',
            'marker' => 'fb_new',
            'order_id' => 123456789,
            'date' => date('Y-m-d H:i:s'),
            'landing_page' => 'test.com/new',
            'domain' => 'test.com',
            'city' => 'Москва',
            'country' => 'Россия',
            'ip' => '161.185.160.93',
            'referrer' => 'fb.com',
            'utm_source' => 'facebook',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'summer_sale',
            'utm_term' => 'купить диван',
            'utm_content' => 'banner_1',
            'status' => 'ANSWER',
            'duration' => 125,
            'link' => 'https://cdn.roistat.com/records/record_313.mp3',
            'file_id' => 'file_313',
        ];

        ProcessRoistatCallJob::dispatch($data)->onQueue('roistat_calls');

        return response()->json([
            'status' => 'test_success',
            'type' => 'call_end',
            'data' => $data,
        ]);
    }

    /**
     * Эмуляция формы (Ловец Лидов)
     */
    public function simulateForm(Request $request)
    {
        $data = [
            'city' => 'Москва',
            'country' => 'Россия',
            'ip' => '85.91.102.232',
            'visit_id' => '100001',
            'first_visit' => '100001',
            'referrer' => 'https://google.com/search',
            'domain' => 'test.com',
            'landing_page' => 'test.com/landing',
            'marker' => 'organic',
            'utm_source' => 'google',
            'utm_medium' => 'organic',
            'utm_term' => 'купить диван москва',
            'google_client_id' => null,
            'metrika_client_id' => '1747953301533923598',
            'name' => 'Алексей',
            'phone' => '+79123456789',
            'email' => 'alex@test.ru',
            'date' => date('Y-m-d H:i:s'),
            'comment' => 'Хочу узнать цену на диван',
        ];

        ProcessRoistatFormJob::dispatch($data)->onQueue('roistat_forms');

        return response()->json([
            'status' => 'test_success',
            'type' => 'form',
            'data' => $data,
        ]);
    }

    /**
     * Эмуляция нотификации
     */
    public function simulateNotification(Request $request)
    {
        $event = $request->get('event', 'proxy_lead_created');
        
        $notifications = [
            'proxy_lead_created' => [
                'notification_event' => 'proxy_lead_created',
                'project_id' => '75718',
                'project_name' => 'test_project',
                'lead_id' => '12345',
            ],
            'proxy_lead_not_sent' => [
                'notification_event' => 'proxy_lead_not_sent',
                'project_id' => '75718',
                'project_name' => 'test_project',
                'error' => 'CRM connection failed',
            ],
            'got_proxy_lead_duplicate' => [
                'notification_event' => 'got_proxy_lead_duplicate',
                'project_id' => '75718',
                'project_name' => 'test_project',
                'lead_id' => '12345',
                'duplicate_lead_id' => '12344',
            ],
            'call_not_answered' => [
                'notification_event' => 'call_not_answered',
                'project_id' => '75718',
                'project_name' => 'test_project',
                'caller' => '+79123456789',
                'callee' => '+74951234567',
                'status' => 'NOANSWER',
            ],
        ];

        $data = $notifications[$event] ?? [
            'notification_event' => 'unknown_event',
            'project_id' => '75718',
            'project_name' => 'test_project',
        ];

        ProcessRoistatNotificationJob::dispatch($data)->onQueue('roistat_notifications');

        return response()->json([
            'status' => 'test_success',
            'type' => 'notification',
            'event' => $event,
            'data' => $data,
        ]);
    }
}