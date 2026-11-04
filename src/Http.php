<?php
declare(strict_types=1);

namespace App;

use JsonException;

final class Http
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    public static function json(int $status, array $body, array $headers = []): never
    {
        http_response_code($status);
        foreach ($headers as $name => $value) {
            header("$name: $value");
        }
        echo json_encode($body, self::JSON_FLAGS);
        exit;
    }

    public static function success(mixed $data, int $status = 200, string $message = 'OK', array $headers = [], array $meta = []): never
    {
        $body = ['status' => 'success', 'message' => $message, 'data' => $data];
        if ($meta !== []) {
            $body['meta'] = $meta;
        }
        self::json($status, $body, $headers);
    }

    public static function error(int $status, string $message, array $errors = [], array $headers = []): never
    {
        $body = ['status' => 'error', 'message' => $message];
        if ($errors !== []) {
            $body['errors'] = $errors;
        }
        self::json($status, $body, $headers);
    }

    // Middleware 1: semua response JSON UTF-8
    public static function jsonHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
    }

    // Middleware 2: hanya method yang diizinkan, selain itu 405 + header Allow
    public static function allowMethods(string ...$allowed): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (!in_array($method, $allowed, true)) {
            self::error(405, 'Method ' . $method . ' tidak diizinkan.', headers: ['Allow' => implode(', ', $allowed)]);
        }
        return $method;
    }

    // Middleware 3: body harus application/json, selain itu 415
    public static function requireJsonContentType(): void
    {
        $type = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
        if ($type !== 'application/json') {
            self::error(415, 'Content-Type harus application/json.');
        }
    }

    // JSON rusak atau bukan object -> 400
    public static function readJsonObject(): array
    {
        try {
            $payload = json_decode(file_get_contents('php://input') ?: '', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            self::error(400, 'Format JSON tidak valid.');
        }

        if (!is_array($payload) || $payload === [] || array_is_list($payload)) {
            self::error(400, 'Body JSON harus berupa object berisi field data.');
        }

        return $payload;
    }
}