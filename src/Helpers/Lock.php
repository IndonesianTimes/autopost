<?php

declare(strict_types=1);

namespace App\Helpers;

class Lock
{
    private string $file;
    private $handle = null;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    public function acquire(): bool
    {
        $this->handle = fopen($this->file, 'c');
        if ($this->handle === false) {
            return false;
        }
        if (!flock($this->handle, LOCK_EX | LOCK_NB)) {
            fclose($this->handle);
            $this->handle = null;
            return false;
        }
        ftruncate($this->handle, 0);
        fwrite($this->handle, (string)getmypid());
        return true;
    }

    public function release(): void
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
    }
}
