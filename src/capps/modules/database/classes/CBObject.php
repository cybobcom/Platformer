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
 * - Soft delete support (deleted, deleted_date)
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

    /**
     * Constructor - supports both patterns
     */
    public function __construct(
        mixed $id = null,
        ?string $table = null,
        ?string $primaryKey = null,
        ?array $dbConfig = null
    ) {
        // Database Connection
        $this->objDatabase = new CBDatabase($dbConfig);

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
        $columns = $this->objDatabase->show("SHOW COLUMNS FROM `{$this->strTable}`");

        $this->arrDatabaseColumns = [];
        foreach ($columns as $column) {
            $this->arrDatabaseColumns[$column['Field']] = $column['Type'];
            $this->arrAttributes[$column['Field']] = '';
        }
    }

    /**
     * Load object - supports _id (int) and _uid (string/UUID)
     */
    public function loadFIRST(mixed $id): bool
    {
        if ($id === null) {
            return false;
        }

        // IMPORTANT: Clear array for loop compatibility (2025-05-14 bob)
        $this->arrAttributes = [];

        // Composite Key Support (e.g. "email:test@example.com")
        if (is_string($id) && str_contains($id, ':')) {
            [$column, $value] = explode(':', $id, 2);
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

            // Set identifier from primary key
            if (isset($id[$this->strPrimaryKey])) {
                $this->identifier = $id[$this->strPrimaryKey];
            }

            return true;
        }

        // Composite Key Support (e.g. "email:test@example.com")
        if (is_string($id) && str_contains($id, ':')) {
            [$column, $value] = explode(':', $id, 2);
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
            $this->arrAttributes[$key] = is_string($value) ? stripslashes($value ?? '') : $value;

            // XML parsing for special fields
            if (in_array($key, ['data', 'media', 'settings']) && !empty($value)) {
                $this->parseXmlField($key, $value);
            }
        }

        // Legacy: addressgroups to array
        if (!empty($this->arrAttributes['addressgroups'])) {
            $this->arrAttributes['arrAddressgroups'] = explode(',', $this->arrAttributes['addressgroups']);
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
            $content = $matches[2][$i];
            $this->arrAttributes[$fieldName . '_' . $tagName] = $content;
        }
    }

    /**
     * Create new record - automatic UUID generation for _uid fields
     */
    public function createFIRST(array $data): int|string|false
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

        // Process XML fields
        $processedData = $this->processXmlFieldsForSave($data);

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
        }
        // Priority 2: Language parameter specified
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

        // Process XML fields
        $processedData = $this->processXmlFieldsForSave($data);

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
     * Update record
     */
    public function updateFIRST(array $data, mixed $id = null): bool
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

        // Process XML fields
        $processedData = $this->processXmlFieldsForSave($data);

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

        // LOCALIZATION HANDLING
        // Priority 1: Explicit localize structure
        if (isset($data['localize']) && is_array($data['localize'])) {
            $localizeXML = $this->buildLocalizeXML($data['localize']);
            $data['localize'] = $localizeXML;
        }
        // Priority 2: Language parameter specified
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
     * Process XML fields for saving with XSS protection
     */
    private function processXmlFieldsForSave(array $data): array
    {
        // Process direct XML field arrays
        foreach (['data', 'media', 'settings'] as $xmlField) {
            if (isset($data[$xmlField]) && is_array($data[$xmlField])) {
                $xml = '';
                foreach ($data[$xmlField] as $key => $value) {
                    // XSS protection: Remove dangerous tags
                    $value = $this->sanitizeXmlValue($value);
                    $xml .= "<{$key}><![CDATA[{$value}]]></{$key}>\n";
                }
                $data[$xmlField] = $xml;
            }
        }

        // Collect individual XML fields (data_*, media_*, settings_*)
        $xmlFields = ['data' => [], 'media' => [], 'settings' => []];

        foreach ($data as $key => $value) {
            foreach (['data_', 'media_', 'settings_'] as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    $xmlType = rtrim($prefix, '_');
                    $fieldName = str_replace($prefix, '', $key);

                    // Skip media_id for capps_media compatibility
                    if ($key === 'media_id') continue;

                    // Apply XSS protection
                    $value = $this->sanitizeXmlValue($value);
                    $xmlFields[$xmlType][$fieldName] = $value;
                    unset($data[$key]);
                }
            }
        }

        // Generate XML strings
        foreach ($xmlFields as $xmlType => $fields) {
            if (!empty($fields)) {
                $xml = '';
                foreach ($fields as $key => $value) {
                    $xml .= "<{$key}><![CDATA[{$value}]]></{$key}>\n";
                }
                $data[$xmlType] = $xml;
            }
        }

        return $data;
    }

    /**
     * XSS protection for XML values
     */
    private function sanitizeXmlValue(mixed $value): string
    {
        if (!is_string($value)) {
            return (string)$value;
        }

        // Remove dangerous HTML tags
        $dangerousTags = [
            '/<script\b[^>]*>.*?<\/script>/is',
            '/<iframe\b[^>]*>.*?<\/iframe>/is',
            '/<object\b[^>]*>.*?<\/object>/is',
            '/<embed\b[^>]*>/i',
            '/<applet\b[^>]*>.*?<\/applet>/is',
            '/on\w+\s*=\s*["\'].*?["\']/i', // Event handlers like onclick, onload
        ];

        foreach ($dangerousTags as $pattern) {
            $value = preg_replace($pattern, '', $value);
        }

        return $value;
    }

    /**
     * Modern findAll - with data_ field sorting (NO automatic soft-delete filtering!)
     */
    public function findAll(array $conditions = [], array $options = []): array
    {

        $where = '';
        $params = [];

        // WHERE conditions
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $key => $value) {
                if (is_array($value)) {
                    $placeholders = array_fill(0, count($value), '?');
                    $whereClauses[] = "`{$key}` IN (" . implode(',', $placeholders) . ")";
                    $params = array_merge($params, $value);
                } else {
                    $whereClauses[] = "`{$key}` = ?";
                    $params[] = $value;
                }
            }
            $where = 'WHERE ' . implode(' AND ', $whereClauses);
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

            foreach ($orderFields as $idx => $field) {
                $dir = strtoupper($directions[$idx] ?? 'ASC');

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

        $limitClause = isset($options['limit']) ? "LIMIT {$options['limit']}" : '';
        $select = $options['select'] ?? '*';

        $query = "SELECT {$select} FROM `{$this->strTable}` {$where} {$normalOrderBy} {$limitClause}";

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
     */
    public function exists(array $conditions): bool
    {
        if (empty($conditions)) {
            throw new InvalidArgumentException("No conditions provided for exists check");
        }

        $where = '';
        $params = [];

        $whereClauses = [];
        foreach ($conditions as $key => $value) {
            $whereClauses[] = "`{$key}` = ?";
            $params[] = $value;
        }
        $where = 'WHERE ' . implode(' AND ', $whereClauses);

        $query = "SELECT COUNT(*) as count FROM `{$this->strTable}` {$where} LIMIT 1";
        $result = $this->objDatabase->selectOne($query, $params);

        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Count records
     */
    public function count(array $conditions = []): int
    {
        $where = '';
        $params = [];

        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $key => $value) {
                if (is_array($value)) {
                    $placeholders = array_fill(0, count($value), '?');
                    $whereClauses[] = "`{$key}` IN (" . implode(',', $placeholders) . ")";
                    $params = array_merge($params, $value);
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
                $insertedIds[] = $this->create($data);
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
     * Legacy getAllEntries - for old code compatibility with data_ sorting
     */
    public function getAllEntries(
        ?string $order = null,
        string $direction = "ASC",
        array $arrCondition = [],
        ?string $selection = null,
        string $result = "",
        ?int $limit = null
    ): array {
        // Delegate to findAll for unified logic
        $options = [];
        if ($order) $options['order'] = $order;
        if ($direction) $options['direction'] = $direction;
        if ($selection) $options['select'] = $selection;
        if ($limit) $options['limit'] = $limit;

        return $this->findAll($arrCondition, $options);
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
            $fieldName = str_replace([$xmlFieldType . '_'], '', $strDataField);

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
     * Smart Save - Automatically detects if Insert or Update is needed
     *
     * @param array $data Data to save
     * @param array $conditions Conditions for identification (for update check)
     * @return int|string|false ID of saved record or false on error
     *
     * @example
     * // Saves or updates user based on email
     * $id = $user->save(
     *     ['name' => 'John', 'email' => 'john@test.com'],
     *     ['email' => 'john@test.com']
     * );
     */
    public function saveFIRST(array $data, array $conditions = []): int|string|false
    {
        if (empty($data)) {
            return false;
        }

        // Check if record already exists
        $existing = null;

        if (!empty($conditions)) {
            // Search by conditions
            $results = $this->findAll($conditions, ['limit' => 1]);
            $existing = !empty($results) ? $results[0] : null;
        } elseif ($this->identifier !== null) {
            // Use current instance ID
            $existing = [$this->strPrimaryKey => $this->identifier];
        }

        // Update or Insert
        if ($existing) {
            // UPDATE
            $id = $existing[$this->strPrimaryKey];
            $data['date_updated'] = date('Y-m-d H:i:s');
            $success = $this->update($data, $id);
            return $success ? $id : false;
        } else {
            // INSERT
            $data = array_merge($data, $conditions); // Take conditions as data
            $data['date_created'] = date('Y-m-d H:i:s');
            return $this->create($data);
        }
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
            $this->strPrimaryKey
        );
    }

    /**
     * Read attribute (Legacy + Modern)
     */
    public function getAttributeFIRST(string $key): mixed
    {
        return $this->arrAttributes[$key] ?? "";
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
     * Set attribute
     */
    public function setAttributeFIRST(string $key, mixed $value): void
    {
        $this->arrAttributes[$key] = $value;
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
                        $result[$langCode][$field[1]] = $field[2];
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
            $xml .= '<lang code="' . $lang . '">';
            foreach ($fields as $key => $value) {
                $xml .= '<' . $key . '><![CDATA[' . $value . ']]></' . $key . '>';
            }
            $xml .= '</lang>';
        }

        return $xml;
    }

}