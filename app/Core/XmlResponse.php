<?php

namespace App\Core;

final class XmlResponse
{
    public static function send(string $body, string $contentType, ?int $modifiedAt = null): void
    {
        $etag = '"' . hash('sha256', $body) . '"';
        header('Content-Type: ' . $contentType . '; charset=utf-8');
        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=300, must-revalidate');
        if ($modifiedAt) header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modifiedAt) . ' GMT');

        $ifNoneMatch = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        $ifModifiedSince = strtotime((string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '')) ?: 0;
        if (($ifNoneMatch !== '' && hash_equals($etag, $ifNoneMatch)) || ($modifiedAt && $ifModifiedSince >= $modifiedAt)) {
            http_response_code(304);
            return;
        }
        echo $body;
    }
}
