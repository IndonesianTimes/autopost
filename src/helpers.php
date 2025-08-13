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
    $header = '';
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $header = $headers['Authorization'];
        }
    }
    if ($header === '' && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['HTTP_AUTHORIZATION'];
    }
    if ($header === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if ($header !== '' && preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
        return trim($matches[1]);
    }
    if (isset($_GET['token'])) {
        return (string)$_GET['token'];
    }
    return null;
}

function nowUTC(): string
{
    return (new DateTime('now', new DateTimeZone('UTC')))->format(DateTime::ATOM);
}
