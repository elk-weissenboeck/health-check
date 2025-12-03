<?php

class myApiAuth
{
    private array $tokens;

    public function __construct(string $tokenConfigPath)
    {
        if (!file_exists($tokenConfigPath)) {
            throw new RuntimeException("Token-Konfigurationsdatei nicht gefunden: {$tokenConfigPath}");
        }

        $tokens = require $tokenConfigPath;

        if (!is_array($tokens)) {
            throw new RuntimeException("Token-Konfiguration muss ein Array zurückgeben.");
        }

        $this->tokens = $tokens;
    }

    /**
     * Liest den Authorization-Header und extrahiert den Bearer-Token.
     */
    private function getBearerTokenFromHeaders(): ?string
    {
        $authorizationHeader = null;

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authorizationHeader = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['Authorization'])) {
                $authorizationHeader = $headers['Authorization'];
            }
        }

        if (!$authorizationHeader) {
            return null;
        }

        // Erwartetes Format: "Bearer <token>"
        if (preg_match('/^Bearer\s+(.+)$/i', $authorizationHeader, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Liefert Informationen zum Client:
     * - kein Token → "anonymous"
     * - gültiger Token → konfigurierter Client
     * - ungültiger Token → null
     */
    public function getClient(): ?array
    {
        $token = $this->getBearerTokenFromHeaders();

        // Optional: Fallback via GET ?token=...
        if ($token === null && isset($_GET['token'])) {
            $token = $_GET['token'];
        }

        // KEIN Token → anonymous-Client
        if ($token === null) {
            return [
                'token' => null,
                'name'  => 'anonymous',
                'roles' => ['anonymous'],
            ];
        }

        // Token wurde übergeben, ist aber unbekannt → ungültig
        if (!isset($this->tokens[$token])) {
            return null;
        }

        $data = $this->tokens[$token];

        return [
            'token' => $token,
            'name'  => $data['name']  ?? 'Unbekannt',
            'roles' => $data['roles'] ?? [],
        ];
    }

    /**
     * Prüft, ob der Client eine bestimmte Rolle hat.
     */
    public function clientHasRole(array $client, string $role): bool
    {
        return in_array($role, $client['roles'], true);
    }

    /**
     * Stellt sicher, dass ein (auch anonymer) Client existiert.
     * Nur bei UNGÜLTIGEM Token (übergeben, aber unbekannt) → 401.
     */
    public function requireClient(): array
    {
        $client = $this->getClient();

        if ($client === null) {
            http_response_code(401);
            header('Content-Type: text/plain; charset=utf-8');
            echo json_encode(['error' => "Nicht authentifiziert (ungültiger Bearer-Token)"]);
            exit;
        }

        return $client;
    }

    /**
     * Stellt sicher, dass der Client eine bestimmte Rolle hat,
     * sonst 403 + Abbruch.
     * Anonymous hat standardmäßig nur Rolle 'anonymous'.
     */
    public function requireRole(string $role): array
    {
        $client = $this->requireClient();

        if (!$this->clientHasRole($client, $role)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo json_encode(['error' => "Keine Berechtigung (Rolle '{$role}' erforderlich)"]);
            exit;
        }

        return $client;
    }
}
