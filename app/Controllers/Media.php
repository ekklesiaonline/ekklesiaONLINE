<?php

namespace App\Controllers;

use App\Services\EventCoverUploadService;
use CodeIgniter\HTTP\ResponseInterface;

class Media extends BaseController
{
    public function eventCover(int $eventId): ResponseInterface
    {
        $event = model('EventModel')->find($eventId);
        if (!is_array($event) || empty($event['cover_path'])) {
            return $this->response->setStatusCode(404);
        }

        $path = EventCoverUploadService::resolveAbsolutePath((string) $event['cover_path'], $eventId);
        if ($path === null || !is_file($path)) {
            return $this->response->setStatusCode(404);
        }

        $mimeType = mime_content_type($path);
        if ($mimeType === false) {
            $mimeType = 'application/octet-stream';
        }

        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setHeader('Cache-Control', 'public, max-age=86400')
            ->setBody(file_get_contents($path));
    }
}
