<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Exceptions\PlatformException;

interface DispatcherInterface
{
    /**
     * Dispatch a payload to a social platform.
     *
     * @param array $payload Structured data for the platform
     * @return array{post_id:?string, response?:mixed}
     *
     * @throws PlatformException
     */
    public function post(array $payload): array;
}
