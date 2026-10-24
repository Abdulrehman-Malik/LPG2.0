<?php

namespace App\Libraries;

use CodeIgniter\Log\Handlers\FileHandler;

/**
 * Structured application log (BL-006): one JSON object per line in writable/logs/log-{date}.log
 * with time, level, message, request ID, signed-in user, branch, method and path.
 */
class JsonFileHandler extends FileHandler
{
    public function handle($level, $message): bool
    {
        $filepath = $this->path . 'log-' . date('Y-m-d') . '.' . $this->fileExtension;
        $newfile = !is_file($filepath);

        $entry = [
            'time' => date('Y-m-d\TH:i:sP'),
            'level' => strtolower((string) $level),
            'message' => (string) $message,
            'request_id' => RequestContext::id(),
        ] + RequestContext::actor() + [
            'method' => $_SERVER['REQUEST_METHOD'] ?? (PHP_SAPI === 'cli' ? 'CLI' : null),
            'path' => isset($_SERVER['REQUEST_URI']) ? strtok((string) $_SERVER['REQUEST_URI'], '?') : null,
        ];
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";

        $fp = @fopen($filepath, 'ab');
        if (!$fp) {
            return false;
        }
        flock($fp, LOCK_EX);
        $result = fwrite($fp, $line);
        flock($fp, LOCK_UN);
        fclose($fp);
        if ($newfile) {
            @chmod($filepath, $this->filePermissions);
        }

        return $result !== false;
    }
}
