<?php

namespace App\Controllers;

class IngestController
{
    public function __invoke(): void
    {
        json(200, ['message' => 'ingest stub']);
    }
}
