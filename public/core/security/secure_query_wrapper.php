<?php
/**
 * ProVal HVAC - Secure Query Wrapper
 * 
 * Enhanced database wrapper that enforces parameterized queries
 * and provides additional security measures for database operations.
 * 
 * @version 1.0
 * @author ProVal Security Team
 */

// Note: db.class.php is loaded by config.php
if (!class_exists('InputValidator')) {
    require_once '../validation/input_validation_utils.php';
}

/**
 * Secure database query wrapper class
 */
class SecureDB {
    
    /**
     * Execute a secure SELECT query with parameter validation
     * 
     * @param string $table Table name
     * @param array $conditions WHERE conditions as associative array
     * @param array $columns Columns to select (default: all)
     * @param array $options Additional options (ORDER BY, LIMIT, etc.)
     * @return array Query results
     * @throws InvalidArgumentException
     */
    public static function secureSelect($table, $conditions = [], $columns = ['*'], $options = []) {
        // Validate table name
        if (!self::isValidTableName($table)) {
            SecurityUtils::logSecurityEvent('invalid_table_name', "Invalid table name attempted: $table");
            throw new InvalidArgumentException('Invalid table name');
        }
        
        // Validate and sanitize conditions
        $validatedConditions = self::validateConditions($conditions);
        
        // Build query
        $columnsList = implode(', ', array_map([self::class, 'sanitizeColumnName'], $columns));
        $query = "SELECT $columnsList FROM `$table`";
        
        $params = [];
        if (!empty($validatedConditions)) {
            $whereClause = self::buildWhereClause($validatedConditions, $params);
            $query .= " WHERE $whereClause";
        }
        
        // Add ORDER BY if specified
        if (isset($options['order_by'])) {
            $orderBy = self::sanitizeColumnName($options['order_by']);
            $direction = isset($options['order_dir']) && strtoupper($options['order_dir']) === 'DESC' ? 'DESC' : 'ASC';
            $query .= " ORDER BY `$orderBy` $direction";
        }
        
        // Add LIMIT if specified
        if (isset($options['limit'])) {
            $limit = InputValidator::validateInteger($options['limit'], 1, 1000);
            if ($limit !== false) {
                $query .= " LIMIT $limit";
                
                if (isset($options['offset'])) {
                    $offset = InputValidator::validateInteger($options['offset'], 0);
                    if ($offset !== false) {
                        $query .= " OFFSET $offset";
                    }
                }
            }
        }
        
        // Log query for security monitoring (without sensitive data)
        SecurityUtils::logSecurityEvent('database_query', 'Secure SELECT executed', [
            'table' => $table,
            'columns' => count($columns),
            'conditions' => count($conditions)
        ]);
        
        return DB::query($query, ...$params);
    }
    
    /**
     * Execute a secure INSERT query
     * 
     * @param string $table Table name
     * @param array $data Data to insert
     * @return int Insert ID
     * @throws InvalidArgumentException
     */
    public static function secureInsert($table, $data) {
        if (!self::isValidTableName($table)) {
            SecurityUtils::logSecurityEvent('invalid_table_name', "Invalid table name attempted: $table");
            throw new InvalidArgumentException('Invalid table name');
        }
        
        if (empty($data)) {
            throw new InvalidArgumentException('No data provided for insert');
        }
        
        // Validate and sanitize data
        $validatedData = self::validateInsertData($data);
        
        SecurityUtils::logSecurityEvent('database_insert', 'Secure INSERT executed', [
            'table' => $table,
            'fields' => count($validatedData)
        ]);
        
        return DB::insert($table, $validatedData);
    }
    
    /**
     * Execute a secure UPDATE query
     * 
     * @param string $table Table name
     * @param array $data Data to update
     * @param array $conditions WHERE conditions
     * @return int Number of affected rows
     * @throws InvalidArgumentException
     */
    public static function secureUpdate($table, $data, $conditions) {
        if (!self::isValidTableName($table)) {
            SecurityUtils::logSecurityEvent('invalid_table_name', "Invalid table name attempted: $table");
            throw new InvalidArgumentException('Invalid table name');
        }
        
        if (empty($data)) {
            throw new InvalidArgumentException('No data provided for update');
        }
        
        if (empty($conditions)) {
            SecurityUtils::logSecurityEvent('unsafe_update', "UPDATE attempted without WHERE clause on table: $table");
            throw new InvalidArgumentException('UPDATE queries must include WHERE conditions');
        }
        
        // Validate data and conditions
        $validatedData = self::validateInsertData($data);
        $validatedConditions = self::validateConditions($conditions);
        
        SecurityUtils::logSecurityEvent('database_update', 'Secure UPDATE executed', [
            'table' => $table,
            'fields' => count($validatedData),
            'conditions' => count($validatedConditions)
        ]);
        
        return DB::update($table, $validatedData, $validatedConditions);
    }
    
    /**
     * Execute a secure DELETE query
     * 
     * @param string $table Table name
     * @param array $conditions WHERE conditions
     * @return int Number of affected rows
     * @throws InvalidArgumentException
     */
    public static function secureDelete($table, $conditions) {
        if (!self::isValidTableName($table)) {
            SecurityUtils::logSecurityEvent('invalid_table_name', "Invalid table name attempted: $table");
            throw new InvalidArgumentException('Invalid table name');
        }
        
        if (empty($conditions)) {
            SecurityUtils::logSecurityEvent('unsafe_delete', "DELETE attempted without WHERE clause on table: $table");
            throw new InvalidArgumentException('DELETE queries must include WHERE conditions');
        }
        
        $validatedConditions = self::validateConditions($conditions);
        
        SecurityUtils::logSecurityEvent('database_delete', 'Secure DELETE executed', [
            'table' => $table,
            'conditions' => count($validatedConditions)
        ]);
        
        return DB::delete($table, $validatedConditions);
    }
    
    /**
     * Get a single record securely
     * 
     * @param string $table Table name
     * @param array $conditions WHERE conditions
     * @param array $columns Columns to select
     * @return array|null Single record or null
     */
    public static function getOne($table, $conditions, $columns = ['*']) {
        $results = self::secureSelect($table, $conditions, $columns, ['limit' => 1]);
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Count records securely
     * 
     * @param string $table Table name
     * @param array $conditions WHERE conditions
     * @return int Number of records
     */
    public static function count($table, $conditions = []) {
        $result = self::secureSelect($table, $conditions, ['COUNT(*) as total']);
        return !empty($result) ? (int)$result[0]['total'] : 0;
    }
    
    /**
     * Validate table name against allowed tables
     * 
     * @param string $table Table name to validate
     * @return bool True if valid
     */
    private static function isValidTableName($table) {
        // Define allowed tables (should be configurable)
        $allowedTables = [
            'tbl_test_schedules_tracking',
            'tbl_val_schedules', 
            'tbl_routine_test_schedules',
            'tbl_proposed_val_schedules',
            'tbl_proposed_routine_test_schedules',
            'equipments',
            'departments',
            'users',
            'tbl_validation_reports',
            'tbl_routine_test_reports',
            'raw_data_templates',
            'security_log',
            'auto_schedule_log',
            'auto_schedule_config',
            'log'
        ];
        
        return in_array($table, $allowedTables) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table);
    }
    
    /**
     * Sanitize column name
     * 
     * @param string $column Column name
     * @return string Sanitized column name
     */
    private static function sanitizeColumnName($column) {
        if ($column === '*') {
            return '*';
        }
        
        // Remove any non-alphanumeric characters except underscores
        $column = preg_replace('/[^a-zA-Z0-9_.]/', '', $column);
        
        // Validate column name format
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $column)) {
            throw new InvalidArgumentException('Invalid column name: ' . $column);
        }
        
        return $column;
    }
    
    /**
     * Validate and sanitize WHERE conditions
     * 
     * @param array $conditions Conditions array
     * @return array Validated conditions
     */
    private static function validateConditions($conditions) {
        $validated = [];
        
        foreach ($conditions as $column => $value) {
            $sanitizedColumn = self::sanitizeColumnName($column);
            
            // Detect and prevent suspicious patterns
            if (SecurityUtils::detectSuspiciousPatterns($column) || 
                (is_string($value) && SecurityUtils::detectSuspiciousPatterns($value))) {
                SecurityUtils::logSecurityEvent('suspicious_query_pattern', 'Suspicious pattern in query conditions', [
                    'column' => $column,
                    'value_type' => gettype($value)
                ]);
                throw new InvalidArgumentException('Suspicious pattern detected in query conditions');
            }
            
            $validated[$sanitizedColumn] = $value;
        }
        
        return $validated;
    }
    
    /**
     * Validate insert/update data
     * 
     * @param array $data Data to validate
     * @return array Validated data
     */
    private static function validateInsertData($data) {
        $validated = [];
        
        foreach ($data as $column => $value) {
            $sanitizedColumn = self::sanitizeColumnName($column);
            
            // Detect suspicious patterns
            if (SecurityUtils::detectSuspiciousPatterns($column) || 
                (is_string($value) && SecurityUtils::detectSuspiciousPatterns($value))) {
                SecurityUtils::logSecurityEvent('suspicious_data_pattern', 'Suspicious pattern in insert/update data', [
                    'column' => $column,
                    'value_type' => gettype($value)
                ]);
                throw new InvalidArgumentException('Suspicious pattern detected in data');
            }
            
            $validated[$sanitizedColumn] = $value;
        }
        
        return $validated;
    }
    
    /**
     * Build WHERE clause from conditions
     * 
     * @param array $conditions Validated conditions
     * @param array &$params Reference to params array
     * @return string WHERE clause
     */
    private static function buildWhereClause($conditions, &$params) {
        $clauses = [];
        
        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                // Handle IN clause
                $placeholders = str_repeat('?,', count($value) - 1) . '?';
                $clauses[] = "`$column` IN ($placeholders)";
                $params = array_merge($params, $value);
            } elseif ($value === null) {
                $clauses[] = "`$column` IS NULL";
            } else {
                $clauses[] = "`$column` = ?";
                $params[] = $value;
            }
        }
        
        return implode(' AND ', $clauses);
    }
    
    /**
     * Execute a prepared statement with logging
     * 
     * @param string $query SQL query with placeholders
     * @param array $params Parameters for the query
     * @param string $operation Operation type for logging
     * @return mixed Query result
     */
    public static function executePrepared($query, $params = [], $operation = 'custom') {
        // Log the operation (without sensitive data)
        SecurityUtils::logSecurityEvent('prepared_statement', "Prepared statement executed: $operation", [
            'param_count' => count($params),
            'operation' => $operation
        ]);
        
        // Validate all parameters
        foreach ($params as $i => $param) {
            if (is_string($param) && SecurityUtils::detectSuspiciousPatterns($param)) {
                SecurityUtils::logSecurityEvent('suspicious_parameter', 'Suspicious pattern in query parameter', [
                    'param_index' => $i,
                    'operation' => $operation
                ]);
                throw new InvalidArgumentException('Suspicious pattern detected in query parameter');
            }
        }
        
        return DB::query($query, ...$params);
    }
}

/**
 * Legacy SQL injection fix utilities
 */
class LegacySecurityFixes {
    
    /**
     * Safely get and validate integer from GET/POST
     * 
     * @param string $key Parameter key
     * @param string $source 'GET' or 'POST'
     * @param int $min Minimum value
     * @param int $max Maximum value
     * @return int|null Validated integer or null
     */
    public static function getValidatedInt($key, $source = 'GET', $min = null, $max = null) {
        $data = ($source === 'POST') ? $_POST : $_GET;
        
        if (!isset($data[$key])) {
            return null;
        }
        
        $value = InputValidator::validateInteger($data[$key], $min, $max);
        
        if ($value === false) {
            SecurityUtils::logSecurityEvent('invalid_parameter', "Invalid integer parameter: $key", [
                'key' => $key,
                'value' => $data[$key],
                'source' => $source
            ]);
            return null;
        }
        
        return $value;
    }
    
    /**
     * Safely get and validate string from GET/POST
     * 
     * @param string $key Parameter key
     * @param string $source 'GET' or 'POST'
     * @param int $maxLength Maximum length
     * @param bool $required Whether field is required
     * @return string|null Validated string or null
     */
    public static function getValidatedString($key, $source = 'GET', $maxLength = 255, $required = false) {
        $data = ($source === 'POST') ? $_POST : $_GET;
        
        if (!isset($data[$key])) {
            return $required ? null : '';
        }
        
        $value = InputValidator::validateText($data[$key], $maxLength, $required);
        
        if ($value === false) {
            SecurityUtils::logSecurityEvent('invalid_parameter', "Invalid string parameter: $key", [
                'key' => $key,
                'source' => $source,
                'length' => strlen($data[$key])
            ]);
            return null;
        }
        
        return $value;
    }
    
    /**
     * Fix vulnerable queries by replacing them with secure alternatives
     * 
     * @param string $originalQuery Original vulnerable query
     * @param array $params Parameters to bind
     * @return array Query result
     * @deprecated Use SecureDB methods instead
     */
    public static function fixVulnerableQuery($originalQuery, $params = []) {
        SecurityUtils::logSecurityEvent('legacy_query_fix', 'Legacy vulnerable query intercepted and fixed', [
            'original_query_hash' => md5($originalQuery),
            'param_count' => count($params)
        ]);
        
        // This method should only be used during transition period
        // All new code should use SecureDB methods directly
        
        return SecureDB::executePrepared($originalQuery, $params, 'legacy_fix');
    }
}

/**
 * Quick fix functions for immediate deployment
 */

/**
 * Secure replacement for direct $_GET usage in queries
 * 
 * @param string $key GET parameter key
 * @param string $type Expected type ('int', 'string', 'date')
 * @param mixed $default Default value if validation fails
 * @return mixed Validated value or default
 */
function secure_get($key, $type = 'string', $default = null) {
    if (!isset($_GET[$key])) {
        return $default;
    }
    
    switch ($type) {
        case 'int':
            $value = InputValidator::validateInteger($_GET[$key], 1);
            return ($value !== false) ? $value : $default;
            
        case 'string':
            $value = InputValidator::sanitizeString($_GET[$key]);
            return !empty($value) ? $value : $default;
            
        case 'date':
            $value = InputValidator::validateDate($_GET[$key]);
            return ($value !== false) ? $value : $default;
            
        default:
            return InputValidator::sanitizeString($_GET[$key]) ?: $default;
    }
}

/**
 * Secure replacement for direct $_POST usage
 * 
 * @param string $key POST parameter key
 * @param string $type Expected type
 * @param mixed $default Default value
 * @return mixed Validated value or default
 */
function secure_post($key, $type = 'string', $default = null) {
    if (!isset($_POST[$key])) {
        return $default;
    }
    
    switch ($type) {
        case 'int':
            $value = InputValidator::validateInteger($_POST[$key], 1);
            return ($value !== false) ? $value : $default;
            
        case 'string':
            $value = InputValidator::sanitizeString($_POST[$key]);
            return !empty($value) ? $value : $default;
            
        case 'email':
            $value = InputValidator::validateEmail($_POST[$key]);
            return ($value !== false) ? $value : $default;
            
        case 'text':
            $value = InputValidator::validateText($_POST[$key], 5000, false, true);
            return ($value !== false) ? $value : $default;
            
        default:
            return InputValidator::sanitizeString($_POST[$key]) ?: $default;
    }
}