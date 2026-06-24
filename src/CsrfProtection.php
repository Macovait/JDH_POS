<?php

namespace JDH\POS;

class CsrfProtection
{
    private static ?string $token = null;

    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        self::$token = $_SESSION['csrf_token'];
    }

    public static function validateToken(): bool
    {
        if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }

    public static function renderTokenField(): string
    {
        $token = self::$token ?? ($_SESSION['csrf_token'] ?? '');
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    public static function getToken(): string
    {
        return self::$token ?? ($_SESSION['csrf_token'] ?? '');
    }
}