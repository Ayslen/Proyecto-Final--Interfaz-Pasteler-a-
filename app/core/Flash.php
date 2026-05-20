<?php

class Flash
{
    public static function set(string $type, string $message): void
    {
        $_SESSION['flash'][] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    public static function get(): array
    {
        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $messages;
    }
}
