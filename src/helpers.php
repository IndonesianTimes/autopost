<?php

function jsonInput(): array
{
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

function jsonResponse($data, int $status = 200): void
{
    json($status, $data);
}

function bearerAuth(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

function nowUTC(): string
{
    return (new DateTime('now', new DateTimeZone('UTC')))->format(DateTime::ATOM);
}
