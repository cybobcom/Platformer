<?php

declare(strict_types=1);

namespace Capps\Modules\Database\Classes;

use InvalidArgumentException;
use RuntimeException;

/**
 * CBObject - Simplified ORM Class
 *
 * Features:
 * - CRUD operations
 * - XML parsing for data_*, media_*, settings_*
 * - Legacy compatibility
 * - Simple attribute management
 * - Auto-timestamps (date_created, date_updated)
 * - Soft delete support (deleted_at)
 * - Transaction support
 * - XSS protection for XML fields
 */
class CBObject
{
    // Core Properties
    public mixed $identifier = null;
    public array $arrAttributes = [];
    public array $arrDatabaseColumns = [];
    public ?CBDatabase $objDatabase = null;
    public ?string $strTable = null;
    public ?string $strPrimaryKey = null;
    protected ?array $dbConfig = null;

    // Encryption: list of field names to auto-encrypt/decrypt
    protected array $arrEncryptedFields = [];

    /**
     * Constructor - supports both patterns
     */
    public function __construct(
        mixed   $id = null,
        ?string $table = null,
        ?string $primaryKey = null,
        ?array  $dbConfig = null
    )
    {
        // Database Connection (shared per config, not one connection per object)
        $this->dbConfig = $dbConfig;
        $this->objDatabase = CBDatabase::shared($dbConfig);

        // Table Setup
        if ($table && $primaryKey) {
            $this->strTable = $table;
            $this->strPrimaryKey = $primaryKey;
            $this->loadTableSchema();

            // Load data if ID provided
            if ($id !== null) {
                $this->identifier = $id;
                $this->load($id);
            }
        }
    }

    /**
     * Get last error message from database
     */
    public function getLastError(): ?string
    {
        return $this->objDatabase->getLastError();
    }

    /**
     * Load table schema
     */
    private function loadTableSchema(): void
    {
        static $schemaCache = []; // Schema pro Tabelle nur einmal pro Request laden
        $cacheKey = $this->objDatabase->getDatabase() . '.' . $this->strTable; // same table name in other DBs
        $columns = $schemaCache[$cacheKey] ??= $this->objDatabase->show("SHOW COLUMNS FROM `{$this->strTable}`");
        if (!$columns) unset($schemaCache[$cacheKey]); // Fehler/leer nicht cachen

        $this->arrDatabaseColumns = [];
        foreach ($columns as $column) {
            $this->arrDatabaseColumns[$column['Field']] = [
                'type' => $column['Type'],
                'null' => $column['Null'] === 'YES',
                'default' => $column['Default']
            ];
            $this->arrAttributes[$column['Field']] = '';
        }
    }


    /**
     * Load object - supports _id, _uid, composite keys, and array
     */
    public function load(mixed $id): bool
    {
        if ($id === null) {
            return false;
        }

        // IMPORTANT: Clear array for loop compatibility (2025-05-14 bob)
        $this->arrAttributes = [];

        // NEW: Array Support (for performance optimization)
        if (is_array($id)) {
            $this->populateFromData($id);

            // Identifier from primary key (null if missing: never keep the previous one)
            $this->identifier = $id[$this->strPrimaryKey] ?? null;

            return true;
        }

        // Composite Key Support (e.g. "email:test@example.com")
        if (is_string($id) && str_contains($id, ':')) {
            [$column, $value] = explode(':', $id, 2);
            if (!isset($this->arrDatabaseColumns[$column])) {
                return false; // only real columns allowed (name goes into the SQL string)
            }
            $data = $this->objDatabase->selectOne(
                "SELECT * FROM `{$this->strTable}` WHERE `{$column}` = ? LIMIT 1",
                [$value]
            );
        } else {
            // Standard: Primary Key Lookup
            $data = $this->objDatabase->selectOne(
                "SELECT * FROM `{$this->strTable}` WHERE `{$this->strPrimaryKey}` = ? LIMIT 1",
                [$id]
            );
        }

        if (!$data) {
            return false;
        }

        $this->populateFromData($data);

        // Identifier is ALWAYS the value of the Primary Key from DB
        $this->identifier = $data[$this->strPrimaryKey];

        return true;
    }

    /**
     * Populate object from data array
     */
    private function populateFromData(array $data): void
    {
        foreach ($data as $key => $value) {
            // No stripslashes: values are used as stored (keeps JSON intact)
            $this->arrAttributes[$key] = $value;

            // XML parsing for special fields
            if (in_array($key, ['data', 'media', 'settings', 'encrypted']) && !empty($value)) {
                $this->parseXmlField($key, $value);
            }
        }

        // Legacy: addressgroups to array
        if (!empty($this->arrAttributes['addressgroups'])) {
            $this->arrAttributes['arrAddressgroups'] = explode(',', $this->arrAttributes['addressgroups']);
        }

        // Encryption: auto-decrypt fields listed in arrEncryptedFields
        foreach ($this->arrEncryptedFields as $field) {
            if (!empty($this->arrAttributes[$field]) && $this->isEncrypted((string)$this->arrAttributes[$field])) {
                $this->arrAttributes[$field] = $this->decryptValue((string)$this->arrAttributes[$field]);
            }
        }

        // Encryption: auto-decrypt encrypted_ XML attributes
        foreach ($this->arrAttributes as $key => $value) {
            if (str_starts_with($key, 'encrypted_') && !empty($value)) {
                $this->arrAttributes[$key] = $this->decryptValue((string)$value);
            }
        }
    }

    /**
     * Parse XML fields
     */
    private function parseXmlField(string $fieldName, string $xmlData): void
    {
        if (empty($xmlData)) return;

        // CDATA pattern matching
        preg_match_all('/\<([^>]+)\>\<!\[CDATA\[(.*?)\]\]>\<\/([^>]+)\>/s', $xmlData, $matches);

        for ($i = 0; $i < count($matches[0]); $i++) {
            $tagName = $matches[1][$i];
            $content = $this->uncdata($matches[2][$i]);
            $this->arrAttributes[$fieldName . '_' . $tagName] = $content;
        }
    }


    /**
     * Create new record with localization support
     *
     * @param array $data Data to create
     * @param string|null $lang Language code for localization
     * @return int|string|false
     */
    public function create(array $data, ?string $lang = null): int|string|false
    {
        if (empty($data)) {
            return false;
        }

        // Add auto-timestamps (if fields exist)
        if ($this->hasColumn('date_created') && !isset($data['date_created'])) {
            $data['date_created'] = date('Y-m-d H:i:s');
        }
        if ($this->hasColumn('date_updated') && !isset($data['date_updated'])) {
            $data['date_updated'] = date('Y-m-d H:i:s');
        }

        // LOCALIZATION HANDLING
        // Priority 1: Explicit localize structure
        if (isset($data['localize']) && is_array($data['localize'])) {
            $localizeXML = $this->buildLocalizeXML($data['localize']);
            $data['localize'] = $localizeXML;
        } // Priority 2: Language parameter specified
        elseif ($lang !== null) {
            $defaultLang = 'de';
            if (class_exists('\Capps\Modules\Core\Classes\CBCore')) {
                $defaultLang = \Capps\Modules\Core\Classes\CBCore::getDefaultLanguage();
            }
            $localizedData = [];

            // Move localizable fields to localize structure
            foreach ($data as $key => $value) {
                // Skip non-localizable fields
                if (in_array($key, ['date_updated', 'date_created', 'active', 'deleted_at'])) {
                    continue;
                }

                // Store in localize structure
                $localizedData[$lang][$key] = $value;

                // If NOT default language, remove from column data
                if ($lang !== $defaultLang && $this->hasColumn($key)) {
                    unset($data[$key]);
                }
            }

            // Build localize XML
            $data['localize'] = $this->buildLocalizeXML($localizedData);
        }

        // Process XML fields (new record: do not merge XML of the currently loaded object)
        $processedData = $this->processXmlFieldsForSave($data, false);

        // NEUE FILTERUNG: Nur gültige DB-Felder durchlassen
        $processedData = $this->filterValidColumns($processedData);

        $processedData = $this->sanitizeDataTypes($processedData); // NEU

        // Auto-generate UUID if Primary Key ends with _uid
        if (str_ends_with($this->strPrimaryKey, '_uid') && !isset($processedData[$this->strPrimaryKey])) {
            $processedData[$this->strPrimaryKey] = $this->objDatabase->generateUuid();
        }

        $insertId = $this->objDatabase->insert($this->strTable, $processedData, $this->strPrimaryKey);

        if ($insertId !== false) {
            $this->identifier = $insertId;
            $this->load($insertId);
        }

        return $insertId;
    }

    /**
     * Legacy wrapper around create().
     *
     * @deprecated Use create() instead.
     * @see create()
     */
    function saveContentNew($arrContent, $_forceOverwritePK = false, $_flagReturnSql = NULL)
    {
        return $this->create($arrContent);
    }



    /**
     * Update record with localization support
     *
     * @param array $data Data to update
     * @param mixed $id ID to update (null = use current identifier)
     * @param string|null $lang Language code for localization (null=default, 'de'/'en'=localize)
     * @return bool
     */
    public function update(array $data, mixed $id = null, ?string $lang = null): bool
    {
        $updateId = $id ?? $this->identifier;

        if ($updateId === null) {
            return false;
        }

        if (empty($data)) {
            return false;
        }

        // Auto-timestamp for update (if field exists)
        if ($this->hasColumn('date_updated') && !isset($data['date_updated'])) {
            $data['date_updated'] = date('Y-m-d H:i:s');
        }

        // XML fields are merged with arrAttributes: for another record use a copy loaded with it
        // (this object stays unchanged; no extra query without XML fields)
        if ($updateId != $this->identifier && preg_grep('/^(data|media|settings|encrypted)(_|$)/', array_map('strval', array_keys($data)))) {
            $target = clone $this;
            $target->load($updateId);
            $target->identifier = $updateId; // also if not found: no recursion
            return $target->update($data, $updateId, $lang);
        }

        // LOCALIZATION HANDLING
        // Priority 1: Explicit localize structure
        if (isset($data['localize']) && is_array($data['localize'])) {
            $localizeXML = $this->buildLocalizeXML($data['localize']);
            $data['localize'] = $localizeXML;
        } // Priority 2: Language parameter specified
        elseif ($lang !== null) {
            $defaultLang = 'de';
            if (class_exists('\Capps\Modules\Core\Classes\CBCore')) {
                $defaultLang = \Capps\Modules\Core\Classes\CBCore::getDefaultLanguage();
            }
            // Load existing localize data
            $this->load($updateId);
            $localizedData = $this->parseLocalizeXML($this->arrAttributes['localize'] ?? '');

            // Update localized fields
            foreach ($data as $key => $value) {
                // Skip non-localizable fields
                if (in_array($key, ['date_updated', 'date_created', 'active', 'deleted_at'])) {
                    continue;
                }

                // Store in localize structure
                $localizedData[$lang][$key] = $value;

                // If this is the default language, also write to column
                if ($lang === $defaultLang && $this->hasColumn($key)) {
                    // Keep in $data for column update
                } else {
                    // Remove from data (only in localize)
                    unset($data[$key]);
                }
            }

            // Build localize XML
            $data['localize'] = $this->buildLocalizeXML($localizedData);
        }

        // Process XML fields
        $processedData = $this->processXmlFieldsForSave($data);

        // NEUE FILTERUNG: Nur gültige DB-Felder durchlassen
        $processedData = $this->filterValidColumns($processedData);

        $processedData = $this->sanitizeDataTypes($processedData); // NEU

        $success = $this->objDatabase->update(
            $this->strTable,
            $processedData,
            "`{$this->strPrimaryKey}` = ?",
            [$updateId]
        );

        if ($success && $updateId == $this->identifier) {
            $this->load($updateId);
        }

        return $success;
    }

    /**
     * Legacy wrapper around update() (note the swapped argument order).
     *
     * @deprecated Use update() instead.
     * @see update()
     */
    function saveContentUpdate($id, $arrContent, $_flagReturnSql = NULL)
    {
        return $this->update($arrContent, $id);
    }

    /**
     * Delete record
     */
    public function delete(mixed $id = null): bool
    {
        $deleteId = $id ?? $this->identifier;

        if ($deleteId === null) {
            return false;
        }

        $success = $this->objDatabase->delete(
            $this->strTable,
            "`{$this->strPrimaryKey}` = ?",
            [$deleteId]
        );

        if ($success && $deleteId == $this->identifier) {
            $this->identifier = null;
            $this->arrAttributes = [];
        }

        return $success;
    }

    /**
     * Legacy wrapper around delete().
     *
     * @deprecated Use delete() instead.
     * @see delete()
     */
    function deleteEntry($id = NULL){
        return $this->delete($id);
    }

    /**
     * Soft Delete - Mark record as deleted (if fields exist)
     */
    public function softDelete(mixed $id = null): bool
    {
        $deleteId = $id ?? $this->identifier;

        if ($deleteId === null) {
            return false;
        }

        // Check if soft delete fields exist
        if (!$this->hasColumn('deleted_at')) {
            return false;
        }

        $data = ['deleted_at' => date('Y-m-d H:i:s')];

        return $this->update($data, $deleteId);
    }

    /**
     * Restore soft deleted record
     */
    public function restoreSoftDeleted(mixed $id = null): bool
    {
        $restoreId = $id ?? $this->identifier;

        if ($restoreId === null) {
            return false;
        }

        // Check if soft delete fields exist
        if (!$this->hasColumn('deleted_at')) {
            return false;
        }

        $data = ['deleted_at' => null];

        return $this->update($data, $restoreId);
    }

    /**
     * Filter data to only valid database columns
     * Keeps: actual DB columns (incl. XML fields data, media, settings, encrypted if the table has them)
     * Removes: invalid fields that would cause SQL errors
     */
    private function filterValidColumns(array $data): array
    {
        $filtered = [];

        foreach ($data as $key => $value) {
            // Allow valid database columns
            if (isset($this->arrDatabaseColumns[$key])) {
                $filtered[$key] = $value;
                continue;
            }

            // Invalid field - ignore silently (KISS Prinzip)
        }

        return $filtered;
    }


    /** $merge = false: build XML from $data only (create), true: merge into stored XML fields (update) */
    private function processXmlFieldsForSave(array $data, bool $merge = true): array
    {
        // Collect all XML fields
        $xmlFields = ['data' => [], 'media' => [], 'settings' => [], 'encrypted' => []];

        // Direkte Arrays einsammeln (data => [...])
        foreach (['data', 'media', 'settings', 'encrypted'] as $xmlField) {
            if (isset($data[$xmlField]) && is_array($data[$xmlField])) {
                $xmlFields[$xmlField] = $data[$xmlField];
                unset($data[$xmlField]);
            }
        }

        // Einzelfelder einsammeln (data_*, media_*, settings_*, encrypted_*)
        foreach ($data as $key => $value) {
            foreach (['data_', 'media_', 'settings_', 'encrypted_'] as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    $xmlType = rtrim($prefix, '_');
                    $fieldName = substr($key, strlen($prefix)); // prefix only at the start
                    if ($key === 'media_id') continue;
                    $xmlFields[$xmlType][$fieldName] = $value;
                    unset($data[$key]);
                }
            }
        }

        // XML bauen mit merge
        foreach ($xmlFields as $xmlType => $fields) {
            if (!empty($fields)) {
                // 1. Bestehende Felder aus arrAttributes holen
                $base = [];
                foreach ($merge ? $this->arrAttributes : [] as $k => $v) {
                    if (str_starts_with($k, $xmlType . '_')) {
                        // arrAttributes holds decrypted values: encrypt again before storing
                        if ($xmlType === 'encrypted' && $v !== '' && !$this->isEncrypted($v)) {
                            $v = $this->encryptValue($v);
                        }
                        $base[substr($k, strlen($xmlType) + 1)] = $v;
                    }
                }
                // 2. Neue Werte drüber mergen
                foreach ($fields as $k => $v) {
                    if ($v === null) {
                        unset($base[$k]);   // null = Feld gezielt aus dem XML entfernen
                    } else {
                        if ($xmlType === 'encrypted') {
                            // Encrypt values, skip XSS sanitize
                            $v = (string)$v; // strict_types: ints etc.
                            $base[$k] = $this->isEncrypted($v) ? $v : $this->encryptValue($v);
                        } else {
                            $base[$k] = $this->sanitizeXmlValue($v);
                        }
                    }
                }
                // 3. XML bauen
                $xml = '';
                foreach ($base as $key => $value) {
                    if (!$this->validXmlKey($key)) continue; // would break the XML structure
                    $xml .= "<{$key}>" . $this->cdata($value) . "</{$key}>\n";
                }
                $data[$xmlType] = $xml;
            }
        }

        // Encryption: auto-encrypt fields listed in arrEncryptedFields
        foreach ($this->arrEncryptedFields as $field) {
            if (!empty($data[$field]) && !$this->isEncrypted((string)$data[$field])) {
                $data[$field] = $this->encryptValue((string)$data[$field]);
            }
        }

        return $data;
    }

    /**
     * Legacy safety net: true if a GET/POST/COOKIE value containing ' " or \\ appears
     * verbatim in $sql, i.e. user input was concatenated into the SQL text.
     * Heuristic: does not detect transformed input or numeric contexts.
     */
    private function embedsRequestInput(string $sql): bool
    {
        $found = false;
        foreach ([$_GET, $_POST, $_COOKIE] as $source) {
            array_walk_recursive($source, function ($v) use ($sql, &$found) {
                $v = is_string($v) ? trim($v) : '';
                if ($v !== '' && strpbrk($v, "'\"\\") !== false && str_contains($sql, $v)) {
                    $found = true;
                }
            });
        }
        return $found;
    }

    /**
     * Wrap a value in CDATA. A literal "]]>" is split so it cannot close the section
     * and inject new XML elements (read back by uncdata()).
     */
    private function cdata(mixed $value): string
    {
        return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', (string)$value) . ']]>';
    }

    /** Reverse of the "]]>" split done in cdata(). */
    private function uncdata(string $value): string
    {
        return str_replace(']]]]><![CDATA[>', ']]>', $value);
    }

    /** XML tag names must not contain < or > (they would break out of the tag). */
    private function validXmlKey(string|int $key): bool
    {
        return $key !== '' && !preg_match('/[<>]/', (string)$key);
    }

    /**
     * XSS safety net for HTML in XML values. NOT a replacement for escaping on output.
     * Removes script/iframe/object/embed/applet, event handler attributes (on*) and
     * javascript:/vbscript:/data:text/html URLs inside tags. Everything else stays untouched.
     */
    private function sanitizeXmlValue(mixed $value): string
    {
        if (!is_string($value)) {
            return (string)$value;
        }

        // Repeat until stable: removing one tag can assemble a new one ("<scr<script></script>ipt>")
        for ($i = 0; $i < 5; $i++) {
            $before = $value;

            // Dangerous elements incl. content, then any leftover opening/closing tags
            $value = preg_replace('#<(script|iframe|object|applet)\b[^>]*>.*?</\1\s*>#is', '', $value) ?? '';
            $value = preg_replace('#</?(?:script|iframe|object|embed|applet)\b[^>]*>#i', '', $value) ?? '';

            // Inside tags only: drop unsafe attributes (never touches text outside of tags)
            $value = preg_replace_callback(
                '/<[a-z][^\s>\/]*(?:"[^"]*"|\'[^\']*\'|[^>"\'])*>/i',
                fn($tag) => preg_replace_callback(
                    '/(?<=[\s"\'\/])([a-z][\w:.-]*)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i',
                    fn($a) => $this->isUnsafeAttribute($a[1], $a[2]) ? '' : $a[0],
                    $tag[0]
                ) ?? '',
                $value
            ) ?? '';

            if ($value === $before) break;
        }

        return $value;
    }

    /** True for on* attributes and URL attributes pointing to javascript:/vbscript:/data:text/html. */
    private function isUnsafeAttribute(string $name, string $rawValue): bool
    {
        $name = strtolower($name);
        if (str_starts_with($name, 'on')) {
            return true;
        }
        if (!in_array($name, ['href', 'src', 'action', 'formaction', 'xlink:href', 'data', 'poster', 'background'], true)) {
            return false;
        }
        // Browsers ignore whitespace/control chars and decode entities inside the scheme
        $url = strtolower(preg_replace('/[\x00-\x20]+/', '', html_entity_decode(trim($rawValue, '"\''), ENT_QUOTES | ENT_HTML5)));
        return (bool)preg_match('#^(javascript|vbscript):|^data:text/html#', $url);
    }

    /**
     * Modern findAll - with data_ field sorting (NO automatic soft-delete filtering!)
     *
     * Condition keys, search fields, order fields and the auto-join table name are
     * validated as plain identifiers; direction is limited to ASC/DESC, limit to numbers.
     * SECURITY: options 'select', 'join' (with spaces) and 'rawWhere' are raw SQL:
     * trusted code only. Never embed user input in them; pass values via 'rawParams'
     * (array for the ? placeholders in 'rawWhere').
     * NOTE: condition/where logic is duplicated in findAll(), exists() and count() on purpose
     * (readability). Keep the three in sync, esp. the validNames() check.
     */
    public function findAll(array $conditions = [], array $options = []): array
    {

        $where = '';
        $params = [];

        if (!$this->objDatabase->validNames(...array_keys($conditions))) {
            return [];
        }

        // WHERE conditions
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $key => $value) {
                // String "NULL" → IS NULL
                if ($value === "NULL" || $value === 'NULL') {
                    $whereClauses[] = "`{$key}` IS NULL";
                }
                // String mit "NOT " Prefix
                elseif (is_string($value) && str_starts_with($value, 'NOT ')) {
                    $cleanValue = substr($value, 4); // "NOT " entfernen

                    // data_ fields → XML LIKE
                    if (str_starts_with($key, 'data_') || str_starts_with($key, 'media_') || str_starts_with($key, 'settings_')) {
                        $xmlField = strstr($key, '_', true); // data, media or settings
                        $fieldName = substr($key, strlen($xmlField) + 1);
                        $whereClauses[] = "`{$xmlField}` NOT LIKE ?";
                        $params[] = "%<{$fieldName}>" . $this->cdata($cleanValue) . "</{$fieldName}>%";
                    }
                    // Normal fields
                    else {
                        $whereClauses[] = "(`{$key}` NOT LIKE ? OR `{$key}` IS NULL)";
                        $params[] = $cleanValue;
                    }
                }
                // Array IN()
                elseif (is_array($value)) {
                    $placeholders = array_fill(0, count($value), '?');
                    $whereClauses[] = "`{$key}` IN (" . implode(',', $placeholders) . ")";
                    $params = array_merge($params, $value);
                }
                // data_/media_/settings_ fields → XML LIKE (positive value)
                elseif (str_starts_with($key, 'data_') || str_starts_with($key, 'media_') || str_starts_with($key, 'settings_')) {
                    $xmlField = strstr($key, '_', true); // data, media or settings
                    $fieldName = substr($key, strlen($xmlField) + 1);
                    $whereClauses[] = "`{$xmlField}` LIKE ?";
                    $params[] = "%<{$fieldName}>" . $this->cdata((string)$value) . "</{$fieldName}>%";
                }
                // Normal =
                else {
                    $whereClauses[] = "`{$key}` = ?";
                    $params[] = $value;
                }
            }
            $where = 'WHERE ' . implode(' AND ', $whereClauses);
        }

        // SEARCH Option - NEU
        if (!empty($options['search'])) {
            $searchTerm = $options['search']['term'] ?? '';
            $searchFields = $options['search']['fields'] ?? [];

            if ($searchTerm && !empty($searchFields)) {
                if (!$this->objDatabase->validNames(...(array)$searchFields)) {
                    return [];
                }
                $searchClauses = [];
                foreach ((array)$searchFields as $field) {
                    $searchClauses[] = "`{$field}` LIKE ?";
                    $params[] = '%' . $searchTerm . '%';
                }
                $searchWhere = '(' . implode(' OR ', $searchClauses) . ')';
                $where .= ($where ? ' AND ' : 'WHERE ') . $searchWhere;
            }
        }

        // Raw WHERE (trusted code only): values belong into 'rawParams' (? placeholders)
        if (!empty($options['rawWhere'])) {
            $rawWhere = $options['rawWhere'];

            // Safety net for the legacy pattern "... LIKE '%" . $_REQUEST['x'] . "%'"
            if ($this->embedsRequestInput($rawWhere)) {
                $this->objDatabase->setError("rawWhere blocked: request input is embedded in the SQL text (table {$this->strTable}), use rawParams");
                return [];
            }

            $where .= ($where ? ' AND ' : 'WHERE ') . '(' . $rawWhere . ')';
            $params = array_merge($params, array_values((array)($options['rawParams'] ?? [])));
        }

        // Sorting: Separate normal fields vs data_ fields
        $normalOrderBy = '';
        $dataOrderFields = [];

        if (!empty($options['order'])) {
            $order = $options['order'];
            $direction = $options['direction'] ?? 'ASC';

            $orderFields = is_string($order) ? explode('|', $order) : [$order];
            $directions = is_string($direction) ? explode('|', $direction) : [$direction];
            $normalOrders = [];

            if (!$this->objDatabase->validNames(...$orderFields)) {
                return [];
            }

            foreach ($orderFields as $idx => $field) {
                $d = $directions[$idx] ?? 'ASC';
                $dir = is_string($d) && strtoupper($d) === 'DESC' ? 'DESC' : 'ASC';

                if (str_starts_with($field, 'data_') || str_starts_with($field, 'media_') || str_starts_with($field, 'settings_')) {
                    // data_ fields for later sorting
                    $dataOrderFields[$field] = $dir;
                } else {
                    // Normal DB fields sort directly
                    $normalOrders[] = "`{$field}` {$dir}";
                }
            }

            if (!empty($normalOrders)) {
                $normalOrderBy = 'ORDER BY ' . implode(', ', $normalOrders);
            }
        }

        $limitClause = '';
        if (isset($options['limit'])) {
            $limit = (string)$options['limit'];
            // Allow "n" or "offset,n"; anything else is cast to int
            $limitClause = 'LIMIT ' . (preg_match('/^\d+(\s*,\s*\d+)?$/', $limit) ? $limit : (int)$limit);
        }
        $select = $options['select'] ?? '*';
        $join = $options['join'] ?? '';

        // Auto-join: nur Tabellenname angegeben (kein Leerzeichen) → Statement automatisch erzeugen
        if ($join !== '' && !str_contains($join, ' ')) {
            if (!$this->objDatabase->validNames($join)) {
                return [];
            }
            $join = "LEFT JOIN {$join} ON {$join}.{$this->strPrimaryKey} = {$this->strTable}.{$this->strPrimaryKey}";
        }

        $query = "SELECT {$select} FROM `{$this->strTable}` {$join} {$where} {$normalOrderBy} {$limitClause}";

        $results = $this->objDatabase->get($query, $params);

        // Apply data_ field sorting
        if (!empty($dataOrderFields) && !empty($results)) {
            foreach ($dataOrderFields as $field => $dir) {
                $results = $this->sortDataParameter($results, $field, $dir);
            }
        }

        return $results;
    }

    /**
     * Check if record exists
     * NOTE: keep in sync with findAll() and count() (duplicated on purpose).
     */
    public function exists(array $conditions): bool
    {
        if (empty($conditions)) {
            throw new InvalidArgumentException("No conditions provided for exists check");
        }
        if (!$this->objDatabase->validNames(...array_keys($conditions))) {
            return false;
        }

        $where = '';
        $params = [];

        $whereClauses = [];
        foreach ($conditions as $key => $value) {
            // String "NULL" → IS NULL
            if ($value === "NULL" || $value === 'NULL') {
                $whereClauses[] = "`{$key}` IS NULL";
            } elseif (is_string($value) && str_starts_with($value, 'NOT ')) {
                $cleanValue = substr($value, 4);
                if (str_starts_with($key, 'data_') || str_starts_with($key, 'media_') || str_starts_with($key, 'settings_')) {
                    $xmlField = strstr($key, '_', true);
                    $fieldName = substr($key, strlen($xmlField) + 1);
                    $whereClauses[] = "`{$xmlField}` NOT LIKE ?";
                    $params[] = "%<{$fieldName}>" . $this->cdata($cleanValue) . "</{$fieldName}>%";
                } else {
                    $whereClauses[] = "(`{$key}` NOT LIKE ? OR `{$key}` IS NULL)";
                    $params[] = $cleanValue;
                }
            } elseif (is_array($value)) {
                $placeholders = array_fill(0, count($value), '?');
                $whereClauses[] = "`{$key}` IN (" . implode(',', $placeholders) . ")";
                $params = array_merge($params, $value);
            } elseif (str_starts_with($key, 'data_') || str_starts_with($key, 'media_') || str_starts_with($key, 'settings_')) {
                // data_/media_/settings_ fields → XML LIKE (positive value)
                $xmlField = strstr($key, '_', true);
                $fieldName = substr($key, strlen($xmlField) + 1);
                $whereClauses[] = "`{$xmlField}` LIKE ?";
                $params[] = "%<{$fieldName}>" . $this->cdata((string)$value) . "</{$fieldName}>%";
            } else {
                $whereClauses[] = "`{$key}` = ?";
                $params[] = $value;
            }
        }
        $where = 'WHERE ' . implode(' AND ', $whereClauses);

        $query = "SELECT COUNT(*) as count FROM `{$this->strTable}` {$where} LIMIT 1";
        $result = $this->objDatabase->selectOne($query, $params);

        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Count records
     * NOTE: keep in sync with findAll() and exists() (duplicated on purpose).
     */
    public function count(array $conditions = []): int
    {
        $where = '';
        $params = [];

        if (!$this->objDatabase->validNames(...array_keys($conditions))) {
            return 0;
        }

        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $key => $value) {
                // String "NULL" → IS NULL
                if ($value === "NULL" || $value === 'NULL') {
                    $whereClauses[] = "`{$key}` IS NULL";
                } elseif (is_string($value) && str_starts_with($value, 'NOT ')) {
                    $cleanValue = substr($value, 4);
                    if (str_starts_with($key, 'data_') || str_starts_with($key, 'media_') || str_starts_with($key, 'settings_')) {
                        $xmlField = strstr($key, '_', true);
                        $fieldName = substr($key, strlen($xmlField) + 1);
                        $whereClauses[] = "`{$xmlField}` NOT LIKE ?";
                        $params[] = "%<{$fieldName}>" . $this->cdata($cleanValue) . "</{$fieldName}>%";
                    } else {
                        $whereClauses[] = "(`{$key}` NOT LIKE ? OR `{$key}` IS NULL)";
                        $params[] = $cleanValue;
                    }
                } elseif (is_array($value)) {
                    $placeholders = array_fill(0, count($value), '?');
                    $whereClauses[] = "`{$key}` IN (" . implode(',', $placeholders) . ")";
                    $params = array_merge($params, $value);
                } elseif (str_starts_with($key, 'data_') || str_starts_with($key, 'media_') || str_starts_with($key, 'settings_')) {
                    // data_/media_/settings_ fields → XML LIKE (positive value)
                    $xmlField = strstr($key, '_', true);
                    $fieldName = substr($key, strlen($xmlField) + 1);
                    $whereClauses[] = "`{$xmlField}` LIKE ?";
                    $params[] = "%<{$fieldName}>" . $this->cdata((string)$value) . "</{$fieldName}>%";
                } else {
                    $whereClauses[] = "`{$key}` = ?";
                    $params[] = $value;
                }
            }
            $where = 'WHERE ' . implode(' AND ', $whereClauses);
        }

        $query = "SELECT COUNT(*) as count FROM `{$this->strTable}` {$where}";
        $result = $this->objDatabase->selectOne($query, $params);

        return (int)($result['count'] ?? 0);
    }

    /**
     * Insert multiple records at once (Bulk Insert)
     */
    public function insertBatch(array $records): array
    {
        if (empty($records)) {
            throw new InvalidArgumentException("No records provided for batch insert");
        }

        $insertedIds = [];

        // Transaction for consistency
        $inTransaction = $this->objDatabase->inTransaction();
        if (!$inTransaction) {
            $this->objDatabase->beginTransaction();
        }

        try {
            foreach ($records as $data) {
                $id = $this->create($data);
                $insertedIds[] = $id;

                // create() returns false on DB errors (no exception): stop, all-or-nothing
                if ($id === false) {
                    if (!$inTransaction) {
                        $this->objDatabase->rollback();
                    }
                    return $insertedIds;
                }
            }

            if (!$inTransaction) {
                $this->objDatabase->commit();
            }

            return $insertedIds;
        } catch (\Exception $e) {
            if (!$inTransaction) {
                $this->objDatabase->rollback();
            }
            throw new RuntimeException("Batch insert failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Legacy wrapper around findAll(), kept for old code.
     *
     * SECURITY: $selection is raw SQL. Never embed user input in it (SQL injection),
     * pass values via $selectionParams and use ? placeholders instead.
     *
     * @deprecated Use findAll() instead (conditions array + 'rawWhere'/'rawParams' options).
     * @see findAll()
     *
     * @param string|null $selection SECURITY: raw SQL, values only via $selectionParams (?)
     * @param array|null $selectionParams Values for the ? placeholders in $selection
     */
    public function getAllEntries(
        ?string $order = null,
        ?string $direction = null,
        ?array $arrCondition = null,
        ?string $selection = null,
        string $result = "",
        ?int $limit = null,
        ?string $join = null,
        ?array $selectionParams = null // values for ? placeholders in $selection (use instead of embedding values)
    ): array {
        // Delegate to findAll for unified logic
        $options = [];
        if ($order) $options['order'] = $order;
        if ($direction) $options['direction'] = $direction;
        if ( $arrCondition == NULL ) $arrCondition = [];
        if ($selection) $options['rawWhere'] = $selection;
        if ($selectionParams) $options['rawParams'] = $selectionParams;
        if ($result) $options['select'] = $result;
        if ($limit) $options['limit'] = $limit;
        if ($join) $options['join'] = $join;

        return $this->findAll($arrCondition, $options);
    }

    /**
     * @deprecated Legacy (portal): first result as object, otherwise an empty instance (never null).
     * @see first()
     */
    public function initObjectWithFirstEntry($order = null, $direction = null, $arrCondition = [], $selection = null, $result = "", $limit = null)
    {
        $rows  = $this->getAllEntries($order, $direction, $arrCondition, $selection, '', 1);
        $class = get_class($this);
        $id    = $rows[0][$this->strPrimaryKey] ?? '';
        return new $class($id !== '' ? $id : null, $this->strTable, $this->strPrimaryKey, $this->dbConfig);
    }

    /**
     * Sort by data_*, media_*, settings_* XML fields
     */
    private function sortDataParameter(array $arrInput, string $strDataField, string $strDirection = "ASC"): array
    {
        if (empty($arrInput)) {
            return [];
        }

        if (empty($strDataField)) {
            return $arrInput;
        }

        // Create sort mapping
        $arrZuordnung = [];

        foreach ($arrInput as $item) {
            $sortValue = '';

            // Determine XML field type (data, media or settings)
            $xmlFieldType = 'data';
            if (str_starts_with($strDataField, 'media_')) {
                $xmlFieldType = 'media';
            } elseif (str_starts_with($strDataField, 'settings_')) {
                $xmlFieldType = 'settings';
            }

            // Extract actual field name (without data_/media_/settings_ prefix)
            $fieldName = substr($strDataField, strlen($xmlFieldType) + 1);

            // Get XML data
            $xmlData = $item[$xmlFieldType] ?? '';

            if (!empty($xmlData)) {
                // Parse XML and extract value
                preg_match_all('/\<' . preg_quote($fieldName) . '\>\<!\[CDATA\[(.*?)\]\]>\<\/' . preg_quote($fieldName) . '\>/s', $xmlData, $matches);
                $sortValue = $matches[1][0] ?? '';
            }

            // Identifier for mapping
            $id = $item[$this->strPrimaryKey] ?? null;
            if ($id !== null) {
                $arrZuordnung[$id] = strtolower($sortValue);
            }
        }

        // Sort
        if (strtoupper($strDirection) === 'ASC') {
            asort($arrZuordnung);
        } else {
            arsort($arrZuordnung);
        }

        // Build result array in sorted order
        $sortedResult = [];
        foreach (array_keys($arrZuordnung) as $id) {
            foreach ($arrInput as $item) {
                if (($item[$this->strPrimaryKey] ?? null) == $id) {
                    $sortedResult[] = $item;
                    break;
                }
            }
        }

        return $sortedResult;
    }


    /**
     * Legacy wrapper around save(). Note: returns nothing (unlike save()).
     *
     * @deprecated Use save() instead.
     * @see save()
     */
    function saveObject($arrCondition, $arrSave){
        $this->save($arrSave,$arrCondition);
    }

    /**
     * Smart Save with localization support
     */
    public function save(array $data, array $conditions = [], ?string $lang = null): int|string|false
    {
        if (empty($data)) {
            return false;
        }

        // Check if record already exists
        $existing = null;

        if (!empty($conditions)) {
            $results = $this->findAll($conditions, ['limit' => 1]);
            if ($this->objDatabase->hasError()) {
                return false; // read failed: do not insert a duplicate
            }
            $existing = !empty($results) ? $results[0] : null;
        } elseif ($this->identifier !== null) {
            $existing = [$this->strPrimaryKey => $this->identifier];
        }

        // Update or Insert
        if ($existing) {
            // UPDATE
            $id = $existing[$this->strPrimaryKey];
            $success = $this->update($data, $id, $lang);
            return $success ? $id : false;
        } else {
            // INSERT
            $data = array_merge($data, $conditions);
            return $this->create($data, $lang);
        }
    }

    /**
     * Load first object from query
     *
     * @param array $conditions WHERE conditions
     * @param array $options Order, Direction, etc.
     * @return static|null New object or null
     *
     * @example
     * // Load first active user
     * $user = (new CBObject(null, 'users', 'user_id'))
     *     ->first(['active' => 1], ['order' => 'name']);
     *
     * @example
     * // Or with specialized class
     * $content = (new Content())->first(['parent_id' => 5]);
     */
    public function first(array $conditions = [], array $options = []): ?self
    {
        $options['limit'] = 1;
        $results = $this->findAll($conditions, $options);

        if (empty($results)) {
            return null;
        }

        // Create new instance of same class
        $class = get_class($this);
        return new $class(
            $results[0][$this->strPrimaryKey],
            $this->strTable,
            $this->strPrimaryKey,
            $this->dbConfig
        );
    }


    /**
     * Get attribute with localization support + fallback chain
     *
     * Fallback priority:
     * 1. localize for current language
     * 2. original column value
     * 3. localize for default language
     *
     * @param string $key Attribute name
     * @param string|false|null $lang Language code (null=auto, false=raw)
     * @return mixed
     */
    public function getAttribute(string $key, $lang = null): mixed
    {
        // Raw mode - no localization
        if ($lang === false) {
            return $this->arrAttributes[$key] ?? "";
        }

        // Auto-detect language
        if ($lang === null) {
            // Try CBCore if available
            if (class_exists('\Capps\Modules\Core\Classes\CBCore')) {
                $lang = \Capps\Modules\Core\Classes\CBCore::getLanguage();
            } else {
                // Fallback: use 'de' as default
                $lang = 'de';
            }
        }

        // Check if localize column exists and has data
        if (!isset($this->arrAttributes['localize']) || empty($this->arrAttributes['localize'])) {
            return $this->arrAttributes[$key] ?? "";
        }

        // Parse localize XML
        $localizedData = $this->parseLocalizeXML($this->arrAttributes['localize']);

        // Priority 1: Localized value for requested language
        if (isset($localizedData[$lang][$key])) {
            return $localizedData[$lang][$key];
        }

        // Priority 2: Original column value
        if (isset($this->arrAttributes[$key]) && !empty($this->arrAttributes[$key])) {
            return $this->arrAttributes[$key];
        }

        // Priority 3: Default language fallback
        $defaultLang = 'de'; // Fallback default
        if (class_exists('\Capps\Modules\Core\Classes\CBCore')) {
            $defaultLang = \Capps\Modules\Core\Classes\CBCore::getDefaultLanguage();
        }

        if ($lang !== $defaultLang && isset($localizedData[$defaultLang][$key])) {
            return $localizedData[$defaultLang][$key];
        }

        // Final fallback
        return $this->arrAttributes[$key] ?? "";
    }

    /**
     * Modern get method
     */
    public function get(string $key): mixed
    {
        return $this->getAttribute($key);
    }


    /**
     * Set attribute with localization support
     *
     * @param string $key Attribute name
     * @param mixed $value Value to set
     * @param string|null $lang Language code (null=current language)
     */
    public function setAttribute(string $key, mixed $value, ?string $lang = null): void
    {
        // If no language specified, set normal attribute
        if ($lang === null) {
            $this->arrAttributes[$key] = $value;
            return;
        }

        // Set localized value in localize column
        $localizedData = $this->parseLocalizeXML($this->arrAttributes['localize'] ?? '');
        $localizedData[$lang][$key] = $value;
        $this->arrAttributes['localize'] = $this->buildLocalizeXML($localizedData);
    }

    /**
     * Modern set method
     */
    public function set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Get all attributes
     */
    public function getAttributes(): array
    {
        return $this->arrAttributes;
    }

    /**
     * Check if attribute exists
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->arrAttributes);
    }

    /**
     * Check if column exists in table
     */
    public function hasColumn(string $column): bool
    {
        return array_key_exists($column, $this->arrDatabaseColumns);
    }

    /**
     * Clear object
     */
    public function reset(): void
    {
        $this->identifier = null;
        $this->arrAttributes = [];

        if (!empty($this->arrDatabaseColumns)) {
            foreach (array_keys($this->arrDatabaseColumns) as $column) {
                $this->arrAttributes[$column] = '';
            }
        }
    }

    /**
     * Debug info
     */
    public function debug(): array
    {
        return [
            'table' => $this->strTable,
            'primary_key' => $this->strPrimaryKey,
            'identifier' => $this->identifier,
            'attributes_count' => count($this->arrAttributes),
            'has_data' => !empty($this->arrAttributes),
            'last_error' => $this->getLastError()
        ];
    }

    /**
     * Legacy raw SELECT without parameter support.
     *
     * SECURITY: $strQuery is executed as is. Never embed user input; use findAll()
     * or $this->objDatabase->get($sql, $params) with ? placeholders instead.
     *
     * @param string $strQuery SECURITY: raw SQL, no user input
     */
    function query($strQuery)
    {
        return $this->objDatabase->get($strQuery);
    }

    /**
     * Parse localize XML column
     */
    private function parseLocalizeXML(string $xml): array
    {
        if (empty($xml)) {
            return [];
        }

        $result = [];

        // Simple XML parsing for <lang code="de"><field>value</field></lang>
        if (preg_match_all('/<lang code="([^"]+)">(.*?)<\/lang>/s', $xml, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $langCode = $match[1];
                $langContent = $match[2];

                // Parse fields within language
                if (preg_match_all('/<([^>]+)><!\[CDATA\[(.*?)\]\]><\/\1>/s', $langContent, $fields, PREG_SET_ORDER)) {
                    foreach ($fields as $field) {
                        $result[$langCode][$field[1]] = $this->uncdata($field[2]);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Build localize XML column
     */
    private function buildLocalizeXML(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $xml = '';
        foreach ($data as $lang => $fields) {
            if (!preg_match('/^[A-Za-z0-9_-]+$/', (string)$lang)) continue; // language code goes into an attribute
            $xml .= '<lang code="' . $lang . '">';
            foreach ($fields as $key => $value) {
                if (!$this->validXmlKey($key)) continue;
                $xml .= '<' . $key . '>' . $this->cdata($value) . '</' . $key . '>';
            }
            $xml .= '</lang>';
        }

        return $xml;
    }



    private function sanitizeDataTypes(array $data): array
    {
        foreach ($data as $col => $val) {
            if (!isset($this->arrDatabaseColumns[$col])) continue;

            $colInfo = $this->arrDatabaseColumns[$col];

            // String "NULL" → NULL
            if ($val === "NULL" || $val === 'NULL') {
                $data[$col] = null;
                continue;
            }

            // Nur für leere Strings weitermachen
            if ($val !== '') continue;

            // Leerer String für INT → NULL oder Default
            if (preg_match('/^(tiny|small|medium|big)?int\b/', strtolower($colInfo['type']))) { // "int(11)" and MySQL 8 "int"
                $data[$col] = $colInfo['null'] ? null : ($colInfo['default'] ?? 0);
            }
            // Leerer String für andere NULL-erlaubte Felder → NULL
            elseif ($colInfo['null']) {
                $data[$col] = null;
            }
        }
        return $data;
    }

    // ================================================================
    // ENCRYPTION HELPERS
    // ================================================================

    /**
     * Get encryption key from config constant (false if not configured)
     */
    protected function getEncryptionKey(): string|false
    {
        if (defined('ENCRYPTION_KEY32') && ENCRYPTION_KEY32 !== '') {
            return ENCRYPTION_KEY32;
        }
        return false;
    }

    /**
     * Encrypt a plaintext value (AES-256-CBC)
     * Returns plaintext unchanged if no key configured
     * Returns plaintext unchanged if no key configurednot
     */
    public function encryptValue(string $plaintext): string
    {
        $key = $this->getEncryptionKey();
        if (!$key) return $plaintext;
        $iv = openssl_random_pseudo_bytes(16);
        $cipher = openssl_encrypt($plaintext, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . '::' . $cipher);
    }

    /**
     * Decrypt an encrypted value
     * Returns value unchanged if not encrypted or no key configured
     */
    public function decryptValue(string $encoded): string
    {
        $key = $this->getEncryptionKey();
        if (!$key) return $encoded;
        // Format: base64( IV[16 bytes] . '::' . cipher ). Parse by fixed position:
        // the random binary IV may itself contain ':' or '::'.
        $raw = base64_decode($encoded);
        if (strlen($raw) < 19 || substr($raw, 16, 2) !== '::') return $encoded;
        return openssl_decrypt(substr($raw, 18), 'AES-256-CBC', $key, 0, substr($raw, 0, 16)) ?: $encoded;
    }

    /**
     * Check if a value is already encrypted
     */
    public function isEncrypted(string $value): bool
    {
        // Same format check as decryptValue(): IV[16 bytes] . '::' . cipher
        $raw = base64_decode($value, true);
        return $raw !== false && strlen($raw) >= 19 && substr($raw, 16, 2) === '::';
    }

}