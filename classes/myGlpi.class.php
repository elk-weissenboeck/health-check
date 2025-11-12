<?php
/**
 * myGlpi.class.php
 *
 * Leichte PHP-Wrapper-Klasse für GLPI REST API auf Basis von myCurl.class.php.
 * - Session-Handling (init/kill)
 * - listSearchOptions
 * - Suche über /search/Ticket (inkl. Tag-Suche, wenn Tag-Plugin aktiv ist)
 *
 * Voraussetzungen:
 *   - myCurl.class.php (muss "myCurl::request($url, $method, $transport, $secrets)" unterstützen
 *     und ein Tupel [HTTP-Code, Content-Type, Body] zurückgeben)
 */

class MyGlpi
{
    private $base;          // z.B. https://glpi.example.com/apirest.php
    private $appToken;      // App-Token aus GLPI
    private $userToken;     // User-Token (oder verwende eine andere Auth-Variante)
    private $sessionToken;  // wird nach initSession gesetzt
    private $searchOptionsCache = []; // Cache für listSearchOptions pro ItemType

    /**
     * @param string $baseURL   Basis-URL inkl. /apirest.php (ohne trailing slash optional)
     * @param string $appToken
     * @param string $userToken
     */
    public function __construct($baseURL, $appToken, $userToken)
    {
        $this->base      = rtrim($baseURL, '/');
        $this->appToken  = $appToken;
        $this->userToken = $userToken;
    }

    /**
     * Initialisiert die GLPI-Session und speichert den Session-Token.
     * Wir rufen initSession nur, wenn noch keine Session existiert.
     */
    public function initSession()
    {
        if (!empty($this->sessionToken)) {
            return $this->sessionToken;
        }

        list($code, $ctype, $body) = $this->request('/initSession', 'POST', [
            'App-Token'     => $this->appToken,
            'Authorization' => 'user_token ' . $this->userToken,
            'Content-Type'  => 'application/json'
        ], (object)[]);

        if ($code !== 200) {
            throw new \RuntimeException("initSession fehlgeschlagen: HTTP $code\n$body");
        }

        $data = json_decode($body, true);
        if (!isset($data['session_token'])) {
            throw new \RuntimeException("Kein session_token in initSession-Antwort:\n$body");
        }

        $this->sessionToken = $data['session_token'];
        return $this->sessionToken;
    }

    /**
     * Beendet die GLPI-Session, falls vorhanden.
     */
    public function killSession()
    {
        if (empty($this->sessionToken)) {
            return;
        }

        $this->request('/killSession', 'POST', [
            'App-Token'     => $this->appToken,
            'Session-Token' => $this->sessionToken,
            'Content-Type'  => 'application/json'
        ], (object)[]);

        $this->sessionToken = null;
    }

    /**
     * Liefert listSearchOptions für ein ItemType (z.B. "Ticket").
     * Ergebnis wird gecached.
     *
     * @param string $itemType
     * @return array assoziatives Array [id => optionArray]
     */
    public function listSearchOptions($itemType = 'Ticket')
    {
        if (!empty($this->searchOptionsCache[$itemType])) {
            return $this->searchOptionsCache[$itemType];
        }

        $this->ensureSession();

        list($code, $ctype, $body) = $this->request("/listSearchOptions/{$itemType}", 'GET', [
            'App-Token'     => $this->appToken,
            'Session-Token' => $this->sessionToken
        ]);

        if ($code !== 200) {
            throw new \RuntimeException("listSearchOptions($itemType) fehlgeschlagen: HTTP $code\n$body");
        }

        $opts = json_decode($body, true) ?: [];
        $this->searchOptionsCache[$itemType] = $opts;
        return $opts;
    }


    // NEU: robustes Auffinden des Tag-Felds
    public function findTagFieldId($itemType = 'Ticket')
    {
        $opts = $this->listSearchOptions($itemType);

        // 1) Suche per Tabellenname des Plugins (stabiler als Übersetzungen)
        foreach ($opts as $id => $opt) {
            $table = isset($opt['table']) ? mb_strtolower($opt['table']) : '';
            if ($table && (strpos($table, 'plugin_tag') !== false || strpos($table, 'glpi_plugin_tag') !== false)) {
                return (string)$id;
            }
        }

        // 2) Fallback: bekannte Übersetzungen von "Tag(s)" prüfen
        $aliases = ['tag','tags','stichwort','stichwörter','schlagwort','schlagwörter','étiquette','étiquettes','etiqueta','etiquetas'];
        foreach ($opts as $id => $opt) {
            $name = isset($opt['name']) ? mb_strtolower($opt['name']) : '';
            if ($name) {
                foreach ($aliases as $needle) {
                    if (mb_strpos($name, $needle) !== false) {
                        return (string)$id;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Findet eine Feld-ID anhand eines (Teil-)Namens (case-insensitive).
     * Beispiel: findFieldIdByName('Ticket', 'tags') -> 123 (wenn Tag-Plugin aktiv ist)
     *
     * @param string $itemType
     * @param string $needle   Suchtext, z.B. "tags" oder "requester"
     * @return string|null     Feld-ID als String, sonst null
     */
    public function findFieldIdByName($itemType, $needle)
    {
        $opts = $this->listSearchOptions($itemType);
        $needle = mb_strtolower($needle);

        foreach ($opts as $id => $opt) {
            $name = isset($opt['name']) ? mb_strtolower($opt['name']) : '';
            if ($name && mb_strpos($name, $needle) !== false) {
                return (string)$id;
            }
        }
        return null;
    }


    public function searchTicketsByTag($tagName, $range = '0-49')
    {
        $tagFieldId = $this->findTagFieldId('Ticket'); // statt findFieldIdByName('Ticket','tag')
        if (!$tagFieldId) {
            throw new \RuntimeException(
                "Tag-Feld nicht gefunden. Prüfe: Plugin aktiv? Tags für 'Ticket' sichtbar? (siehe Checkliste)"
            );
        }

        return $this->searchTickets(
            [
                ['field' => $tagFieldId, 'searchtype' => 'contains', 'value' => $tagName]
            ],
            [$tagFieldId],
            $range,
            'date_mod',
            'DESC'
        );
    }
    
    public function getTicket($id, $expandDropdowns = true)
    {
        $this->ensureSession();
        $qs = $expandDropdowns ? '?expand_dropdowns=true' : '';
        list($code,, $body) = $this->request("/Ticket/".(int)$id . $qs, 'GET', [
            'App-Token'     => $this->appToken,
            'Session-Token' => $this->sessionToken
        ]);
        if ($code !== 200) throw new \RuntimeException("GET /Ticket/$id fehlgeschlagen: $body");
        return json_decode($body, true) ?: [];
    }

    public function getTicketActors($id)
    {
        $this->ensureSession();
        // Subressource liefert Ticket_User; mit expand_dropdowns=true bekommst du u.a. den Usernamen mit
        list($code,, $body) = $this->request("/Ticket/".(int)$id."/Ticket_User?expand_dropdowns=true", 'GET', [
            'App-Token'     => $this->appToken,
            'Session-Token' => $this->sessionToken
        ]);
        if ($code !== 200) throw new \RuntimeException("GET /Ticket/$id/Ticket_User fehlgeschlagen: $body");
        return json_decode($body, true) ?: [];
    }

    public function getTicketRequesters($id)
    {
        $actors = $this->getTicketActors($id);
        $names  = [];
        foreach ($actors as $a) {
            // type==1 => Requester (Anforderer)
            if ((int)($a['type'] ?? 0) === 1) {
                // Mit expand_dropdowns liefert GLPI meist 'user_name' oder 'users_id' + 'users_id_label'
                $names[] = $a['user_name'] ?? $a['users_id_label'] ?? (string)($a['users_id'] ?? '');
            }
        }
        return $names;
    }

    /**
     * Sucht Tickets über den /search/Ticket-Endpoint.
     *
     * @param array $criteria       Array von Kriterien. Jedes Kriterium:
     *                              [
     *                                'field' => (int|string) Feld-ID ODER Feldname (wird versucht, aufzulösen),
     *                                'searchtype' => 'contains'|'equals'|...,
     *                                'value' => 'Suchwert'
     *                              ]
     * @param array $forcedisplay   Liste von Feld-IDs, die zusätzlich mit ausgegeben werden sollen.
     * @param string $range         Paging in "start-end", z.B. "0-49"
     * @param string $sort          Feldname/ID zum Sortieren (z.B. 'date_mod')
     * @param string $order         'ASC' oder 'DESC'
     * @return array                Decodierte GLPI-Antwort (inkl. totalcount, data, etc.)
     */
    public function searchTickets(array $criteria = [], array $forcedisplay = [], $range = '0-49', $sort = 'date_mod', $order = 'DESC')
    {
        $this->ensureSession();

        // Falls Kriterien einen String-Feldnamen enthalten, versuchen wir ihn in eine ID aufzulösen
        $resolved = [];
        if (!empty($criteria)) {
            $opts = $this->listSearchOptions('Ticket');
            $resolved = $this->resolveCriteriaFieldIds($criteria, $opts);
        }

        // Query-Params aufbauen
        $params = [
            'range' => $range,
            'order' => $order,
            'sort'  => $sort
        ];

        // criteria[i][field], criteria[i][searchtype], criteria[i][value]
        foreach (array_values($resolved ?: $criteria) as $i => $c) {
            if (!isset($c['field'], $c['searchtype'], $c['value'])) {
                throw new \InvalidArgumentException("Ungültiges Kriterium an Index $i: erwartet keys field, searchtype, value");
            }
            $params["criteria[$i][field]"]      = (string)$c['field'];
            $params["criteria[$i][searchtype]"] = (string)$c['searchtype'];
            $params["criteria[$i][value]"]      = (string)$c['value'];
        }

        // forcedisplay[j]
        foreach (array_values($forcedisplay) as $j => $fid) {
            $params["forcedisplay[$j]"] = (string)$fid;
        }

        $url = $this->base . '/search/Ticket?' . http_build_query($params);

        list($code, $ctype, $body) = $this->request($url, 'GET', [
            'App-Token'     => $this->appToken,
            'Session-Token' => $this->sessionToken
        ]);

        if ($code !== 200) {
            throw new \RuntimeException("search/Ticket fehlgeschlagen: HTTP $code\n$body");
        }

        return json_decode($body, true) ?: [];
    }

    /**
     * Optional: rohe Ticketliste (/Ticket) holen (ohne Suchlogik).
     *
     * @param string $range
     * @param bool   $expandDropdowns
     * @return array
     */
    public function listTicketsRaw($range = '0-49', $expandDropdowns = true)
    {
        $this->ensureSession();

        $params = ['range' => $range];
        if ($expandDropdowns) {
            $params['expand_dropdowns'] = 'true';
        }
        $url = $this->base . '/Ticket?' . http_build_query($params);

        list($code, $ctype, $body) = $this->request($url, 'GET', [
            'App-Token'     => $this->appToken,
            'Session-Token' => $this->sessionToken
        ]);

        if ($code !== 200) {
            throw new \RuntimeException("GET /Ticket fehlgeschlagen: HTTP $code\n$body");
        }

        return json_decode($body, true) ?: [];
    }

    /**
     * --------- Hilfsfunktionen ----------
     */

    private function ensureSession()
    {
        if (empty($this->sessionToken)) {
            $this->initSession();
        }
    }

    private function resolveCriteriaFieldIds(array $criteria, array $options)
    {
        // Map: name(lower) => id (bei Mehrdeutigkeiten wird der erste Treffer genommen)
        $nameToId = [];
        foreach ($options as $id => $opt) {
            if (!isset($opt['name'])) continue;
            $nameToId[mb_strtolower($opt['name'])] = (string)$id;
        }

        $out = [];
        foreach ($criteria as $c) {
            $c = array_change_key_case($c, CASE_LOWER);
            if (!isset($c['field'])) { $out[] = $c; continue; }
            $field = $c['field'];
            if (!ctype_digit((string)$field)) {
                $key = mb_strtolower($field);
                if (isset($nameToId[$key])) {
                    $c['field'] = $nameToId[$key];
                }
            }
            $out[] = $c;
        }
        return $out;
    }

    /**
     * Zentrale Request-Methode via myCurl.
     *
     * @param string       $path   Entweder relativer API-Pfad (beginnend mit "/") oder vollständige URL
     * @param string       $method GET|POST|PUT|DELETE
     * @param array        $headers Assoziativ: ["Key" => "Value"]
     * @param object|array $jsonBody (optional) wird als JSON gesendet
     * @return array [httpCode, contentType, bodyString]
     */
    private function request($path, $method = 'GET', array $headers = [], $jsonBody = null)
    {
        $url = (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0)
            ? $path
            : $this->base . $path;

        $transport = ['headers' => $headers];

        if ($jsonBody !== null) {
            $transport['body'] = ['json' => $jsonBody];
            if (!isset($transport['headers']['Content-Type'])) {
                $transport['headers']['Content-Type'] = 'application/json';
            }
        }

        // $secrets wird hier nicht genutzt
        return myCurl::request($url, $method, $transport, []);
    }

    /**
     * Schließt die Session automatisch, wenn das Objekt zerstört wird.
     */
    public function __destruct()
    {
        try { $this->killSession(); } catch (\Throwable $e) { /* schlucken */ }
    }
}
