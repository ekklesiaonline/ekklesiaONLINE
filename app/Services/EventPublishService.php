<?php

namespace App\Services;

class EventPublishService
{
    public static function publishDueDraftEvents(): void
    {
        $db = db_connect();
        try {
            if (!$db->fieldExists('publish_at', 'event')) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        $db->table('event')
            ->where('status', 'draft')
            ->where('publish_at IS NOT NULL', null, false)
            ->where('publish_at <=', date('Y-m-d H:i:s'))
            ->update(['status' => 'published']);
    }
}
