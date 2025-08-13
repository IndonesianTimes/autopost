<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class PlatformException extends Exception
{
    public string $platform;
    public array $response;

    public function __construct(string $platform, int $code, string $message, array $response = [])
    {
        parent::__construct($message, $code);
        $this->platform = $platform;
        $this->response = $response;
    }
}
