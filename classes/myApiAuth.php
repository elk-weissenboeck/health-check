<?php

class myApiAuth
{
    private array $tokens;
    private ?string $logFile;
    private ?string $tokenOverride = null; 
    
    public function __construct(string $tokenConfigPath,?string $logFilePath = null)
    {
        if (!file_exists($tokenConfigPath)) {
            throw new RuntimeException("Token-Konfigurationsdatei nicht gefunden: {$tokenConfigPath}");
        }

        $tokens = require $tokenConfigPath;

        if (!is_array($tokens)) {
            throw new RuntimeException("Token-Konfiguration muss ein Array zurückgeben.");
        }

        $this->tokens = $tokens;
        $this->logFile = $logFilePath; // kann null sein -> kein Logging
    }

    /**
     * Liest Token in folgender Priorität:
     * 1. Manuell gesetztes Override (z.B. aus Cookie via useCookieToken())
     * 2. Authorization: Bearer <token>
     * 3. GET-Parameter ?token=...
     */
    private function getTokenString(): ?string
    {
        // 1) Override (z.B. Cookie)
        if ($this->tokenOverride !== null && $this->tokenOverride !== '') {
            return $this->tokenOverride;
        }

        // 2) Authorization-Header (Bearer)
        $authorizationHeader = null;

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authorizationHeader = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['Authorization'])) {
                $authorizationHeader = $headers['Authorization'];
            }
        }

        $token = null;

        if ($authorizationHeader) {
            if (preg_match('/^Bearer\s+(.+)$/i', $authorizationHeader, $matches)) {
                $token = trim($matches[1]);
            }
        }

        // 3) Optional: Fallback via GET ?token=...
        if ($token === null && isset($_GET['token'])) {
            $token = $_GET['token'];
        }

        return $token !== '' ? $token : null;
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
     * - kein Token -> anonymous
     * - gültiger Token -> konfigurierter Client
     * - ungültiger Token -> null
     */
    public function getClient(): ?array
    {
        $token = $this->getTokenString();

        // KEIN Token → anonymous
        if ($token === null) {
            return [
                'token' => null,
                'name'  => 'anonymous',
                'roles' => ['anonymous'],
            ];
        }

        // Token übergeben, aber unbekannt → ungültig
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
            // ungültiger Token: pseudo-"Client" für Logging bauen
            $rawToken = $this->getTokenString();

            $pseudoClient = [
                'token' => $rawToken ?? '-',
                'name'  => 'invalid-token',
                'roles' => [],
            ];

            // hier wird der fehlgeschlagene Versuch protokolliert
            $this->logAction($pseudoClient, 'auth', 'invalid_token');

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
        $client = $this->requireClient(); // loggt invalid_token schon selbst

        if (!$this->clientHasRole($client, $role)) {
            // fehlende Berechtigung protokollieren
            $this->logAction($client, 'requireRole:' . $role, 'denied');

            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo json_encode(['error' => "Keine Berechtigung (Rolle '{$role}' erforderlich)"]);
            exit;
        }

        return $client;
    }

    
    /**
    * Prüft, ob der Client mindestens eine der übergebenen Rollen hat.
    */
    public function clientHasAnyRole(array $client, array $roles): bool
    {
        foreach ($roles as $role) {
            if (in_array($role, $client['roles'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Stellt sicher, dass der Client mindestens eine der angegebenen Rollen hat.
     * Beispiel: requireAnyRole('admin', 'editor')
     */
    public function requireAnyRole(array $roles): array
    {
        $client = $this->requireClient(); // invalid_token wird schon geloggt

        if (!$this->clientHasAnyRole($client, $roles)) {
            $this->logAction($client, 'requireAnyRole:' . implode('|', $roles), 'denied');

            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');

            $roleList = implode("' oder '", $roles);
            echo json_encode(['error' => "Keine Berechtigung; Eine dieser Rollen erforderlich: '{$roleList}')"]);
            exit;
        }

        return $client;
    }

    
    /**
     * Aktion protokollieren: wer, wann, was.
     * $status kannst du z.B. 'ok', 'denied', 'error' etc. geben.
     */
    public function logAction(array $client, string $action, string $status = 'ok'): void
    {
        if ($this->logFile === null) {
            return; // Logging deaktiviert
        }

        $timestamp = date('c'); // ISO 8601
        $ip        = $_SERVER['REMOTE_ADDR'] ?? '-';
        $token     = $client['token'] ?? '-';
        $name      = $client['name']  ?? '-';
        $roles     = implode(',', $client['roles'] ?? []);

        // Tabs und Zeilenumbrüche aus Textfeldern entfernen/sanitizen
        $sanitize = function (string $value): string {
            $value = str_replace(["\t", "\r", "\n"], ' ', $value);
            return trim($value);
        };

        $ip    = $sanitize($ip);
        $token = $sanitize($token);
        $name  = $sanitize($name);
        $roles = $sanitize($roles);
        $action = $sanitize($action);
        $status = $sanitize($status);

        $line = sprintf(
            "%s\t%s\t%s\t%s\t%s\t%s\t%s\n",
            $timestamp,
            $ip,
            $token,
            $name,
            $roles,
            $action,
            $status
        );

        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Nutzt (falls vorhanden) das Cookie "UserToken" als Token-Quelle.
     * Für direkte Aufrufe im Frontend (Browser).
     *
     * Muss VOR requireClient()/requireRole()/requireAnyRole() aufgerufen werden.
     */
    public function useCookieToken(string $cookieName = 'UserToken'): void
    {
        if (isset($_COOKIE[$cookieName]) && $_COOKIE[$cookieName] !== '') {
            $this->tokenOverride = (string)$_COOKIE[$cookieName];
        }
    }
}
