<?php

final class myCache
{
    public const DEFAULT_TTL = 600; // Sekunden (10 Minuten)
    public const DIR         = __DIR__ . '/../cache';
    public const DEBUG       = false;

    /**
     * Berechnet Cache-Dateipfad anhand Methode, finaler URL und Target-Key.
     */
    public static function path(string $method, string $finalUrl, string $targetKey): string
    {
        $dir  = self::DIR ?: sys_get_temp_dir();
        $hash = hash('sha256', $method . "\n" . $finalUrl . "\n" . $targetKey);
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'proxycache_' . $hash . '.json';
    }

    public static function read(string $path): ?array
    {
        if (!is_file($path)) return null;
        $raw = @file_get_contents($path);
        if ($raw === false) return null;
        $data = json_decode($raw, true);
        if (!is_array($data)) return null;
        if (time() > (int)($data['expires_at'] ?? 0)) return null;
        return $data;
    }

    public static function write(string $path, array $payload): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $ttl = (int)($payload['ttl'] ?? self::DEFAULT_TTL);
        $payload['expires_at'] = time() + $ttl;
        @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /**
     * Prüft, ob Caching für dieses Target/Request erlaubt ist.
     */
    public static function isAllowed(array $t, string $method, bool $noCache): bool
    {
        $ttl        = (int)($t['cache']['ttl'] ?? self::DEFAULT_TTL);
        $enabled    = ($t['cache']['enabled'] ?? true);
        $methodOkay = in_array($method, ['GET','HEAD'], true);
        return $enabled && !$noCache && $ttl > 0 && $methodOkay;
    }

    /**
     * Ermittelt TTL für dieses Target.
     */
    public static function ttl(array $t): int
    {
        return (int)($t['cache']['ttl'] ?? self::DEFAULT_TTL);
    }
}