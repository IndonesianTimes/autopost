<?php
declare(strict_types=1);

namespace App\Helpers;

final class Lock
{
    private $fp = null;
    private string $path = '';

    private static function lockFile(string $name): string
    {
        // 1) ENV override
        $dir = getenv('SAE_LOCK_DIR');

        // 2) OS temp dir
        if (!$dir) {
            $dir = sys_get_temp_dir(); // e.g. C:\Windows\Temp on Windows
        }

        // 3) Fallback ke project storage/locks
        if (!$dir || !is_dir($dir) || !is_writable($dir)) {
            $dir = __DIR__ . '/../../storage/locks';
        }

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $name . '.lock';
    }

    public function acquire(string $name = 'sae_worker'): bool
    {
        $this->path = self::lockFile($name);
        $this->fp   = @fopen($this->path, 'c+'); // create if not exists
        if (!$this->fp) {
            throw new \RuntimeException("Cannot open lock file: {$this->path}");
        }

        // Non-blocking exclusive lock
        if (!flock($this->fp, LOCK_EX | LOCK_NB)) {
            // Optional: detect stale lock by age
            $age = @time() - @filemtime($this->path);
            if ($age !== false && $age > 6 * 3600 && getenv('SAE_FORCE_LOCK_CLEAR') === '1') {
                // stale old lock, try to break
                @flock($this->fp, LOCK_UN);
                @fclose($this->fp);
                @unlink($this->path);
                // retry
                $this->fp = @fopen($this->path, 'c+');
                if ($this->fp && flock($this->fp, LOCK_EX | LOCK_NB)) {
                    fwrite($this->fp, (string)getmypid());
                    return true;
                }
            }
            return false; // already locked by another process
        }

        // mark owner pid
        ftruncate($this->fp, 0);
        fwrite($this->fp, (string)getmypid());
        fflush($this->fp);
        return true;
    }

    public function release(): void
    {
        if ($this->fp) {
            @flock($this->fp, LOCK_UN);
            @fclose($this->fp);
            $this->fp = null;
        }
        if ($this->path && is_file($this->path)) {
            @unlink($this->path);
        }
    }
}
