<?php

namespace OhaGui\Core;

use Exception;

/**
 * Configuration Validator for JSON schema validation and error handling
 * Provides comprehensive validation for configuration file structure and content
 */
class ConfigurationValidator
{
    /**
     * JSON Schema for test configuration files
     */
    private const CONFIGURATION_SCHEMA = [
        'type' => 'object',
        'required' => ['name', 'url', 'method', 'concurrentConnections', 'duration', 'timeout'],
        'properties' => [
            'name' => [
                'type' => 'string',
                'minLength' => 1,
                'maxLength' => 100,
                'pattern' => '^[a-zA-Z0-9_\-\.\s]+$'
            ],
            'url' => [
                'type' => 'string',
                'format' => 'uri',
                'minLength' => 1
            ],
            'method' => [
                'type' => 'string',
                'enum' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH']
            ],
            'concurrentConnections' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 1000
            ],
            'duration' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 3600
            ],
            'timeout' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 300
            ],
            'headers' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'patternProperties' => [
                        '^.+$' => [
                            'type' => 'string'
                        ]
                    ]
                ]
            ],
            'body' => [
                'type' => 'string'
            ],
            'createdAt' => [
                'type' => 'string',
                'format' => 'date-time'
            ],
            'updatedAt' => [
                'type' => 'string',
                'format' => 'date-time'
            ]
        ],
        'additionalProperties' => false
    ];

    /**
     * Validate configuration data against JSON schema
     * 
     * @param array $data Configuration data to validate
     * @return array Array of validation errors (empty if valid)
     */
    public function validateConfigurationData(array $data): array
    {
        $errors = [];

        try {
            // Basic structure validation
            $structureErrors = $this->validateStructure($data);
            $errors = array_merge($errors, $structureErrors);

            // If structure is invalid, don't proceed with detailed validation
            if (!empty($structureErrors)) {
                return $errors;
            }

            // Detailed field validation
            $fieldErrors = $this->validateFields($data);
            $errors = array_merge($errors, $fieldErrors);

            // Business logic validation
            $businessErrors = $this->validateBusinessRules($data);
            $errors = array_merge($errors, $businessErrors);

        } catch (Exception $e) {
            $errors[] = 'Validation error: ' . $e->getMessage();
        }

        return $errors;
    }

    /**
     * Validate JSON content structure
     * 
     * @param string $jsonContent JSON string to validate
     * @return array Array of validation errors (empty if valid)
     */
    public function validateJsonContent(string $jsonContent): array
    {
        $errors = [];

        // Check if content is empty
        if (empty(trim($jsonContent))) {
            $errors[] = 'Configuration content is empty';
            return $errors;
        }

        // Validate JSON syntax
        $data = json_decode($jsonContent, true);
        $jsonError = json_last_error();

        if ($jsonError !== JSON_ERROR_NONE) {
            $errors[] = 'Invalid JSON syntax: ' . $this->getJsonErrorMessage($jsonError);
            return $errors;
        }

        // Validate data structure
        if (!is_array($data)) {
            $errors[] = 'Configuration must be a JSON object';
            return $errors;
        }

        // Validate against schema
        $schemaErrors = $this->validateConfigurationData($data);
        $errors = array_merge($errors, $schemaErrors);

        return $errors;
    }

    /**
     * Validate basic structure requirements
     * 
     * @param array $data Configuration data
     * @return array Validation errors
     */
    private function validateStructure(array $data): array
    {
        $errors = [];
        $requiredFields = self::CONFIGURATION_SCHEMA['required'];

        // Check required fields
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data)) {
                $errors[] = "Required field '{$field}' is missing";
            }
        }

        // Check for unexpected fields
        $allowedFields = array_keys(self::CONFIGURATION_SCHEMA['properties']);
        foreach ($data as $field => $value) {
            if (!in_array($field, $allowedFields)) {
                $errors[] = "Unknown field '{$field}' is not allowed";
            }
        }

        return $errors;
    }

    /**
     * Validate individual fields
     * 
     * @param array $data Configuration data
     * @return array Validation errors
     */
    private function validateFields(array $data): array
    {
        $errors = [];

        // Validate name
        if (isset($data['name'])) {
            $nameErrors = $this->validateName($data['name']);
            $errors = array_merge($errors, $nameErrors);
        }

        // Validate URL
        if (isset($data['url'])) {
            $urlErrors = $this->validateUrl($data['url']);
            $errors = array_merge($errors, $urlErrors);
        }

        // Validate HTTP method
        if (isset($data['method'])) {
            $methodErrors = $this->validateMethod($data['method']);
            $errors = array_merge($errors, $methodErrors);
        }

        // Validate numeric fields
        $numericFields = [
            'concurrentConnections' => [1, 1000],
            'duration' => [1, 3600],
            'timeout' => [1, 300]
        ];

        foreach ($numericFields as $field => [$min, $max]) {
            if (isset($data[$field])) {
                $numericErrors = $this->validateNumericField($field, $data[$field], $min, $max);
                $errors = array_merge($errors, $numericErrors);
            }
        }

        // Validate headers
        if (isset($data['headers'])) {
            $headerErrors = $this->validateHeaders($data['headers']);
            $errors = array_merge($errors, $headerErrors);
        }

        // Validate body
        if (isset($data['body'])) {
            $bodyErrors = $this->validateBody($data['body'], $data['method'] ?? 'GET');
            $errors = array_merge($errors, $bodyErrors);
        }

        // Validate timestamps
        foreach (['createdAt', 'updatedAt'] as $field) {
            if (isset($data[$field])) {
                $timestampErrors = $this->validateTimestamp($field, $data[$field]);
                $errors = array_merge($errors, $timestampErrors);
            }
        }

        return $errors;
    }

    /**
     * Validate business rules
     * 
     * @param array $data Configuration data
     * @return array Validation errors
     */
    private function validateBusinessRules(array $data): array
    {
        $errors = [];

        // Validate that body is only provided for methods that support it
        $methodsWithBody = ['POST', 'PUT', 'PATCH'];
        if (!empty($data['body']) && !in_array($data['method'] ?? '', $methodsWithBody)) {
            $errors[] = "Request body is not allowed for {$data['method']} method";
        }

        // Validate timeout is not greater than duration
        if (isset($data['timeout']) && isset($data['duration'])) {
            if ($data['timeout'] > $data['duration']) {
                $errors[] = 'Timeout cannot be greater than test duration';
            }
        }

        // Validate reasonable concurrent connections for duration
        if (isset($data['concurrentConnections']) && isset($data['duration'])) {
            $totalRequests = $data['concurrentConnections'] * $data['duration'];
            if ($totalRequests > 100000) {
                $errors[] = 'Configuration may generate excessive load (consider reducing concurrent connections or duration)';
            }
        }

        return $errors;
    }

    /**
     * Validate configuration name
     */
    private function validateName(mixed $name): array
    {
        $errors = [];

        if (!is_string($name)) {
            $errors[] = 'Configuration name must be a string';
            return $errors;
        }

        if (empty(trim($name))) {
            $errors[] = 'Configuration name cannot be empty';
        }

        if (strlen($name) > 100) {
            $errors[] = 'Configuration name cannot exceed 100 characters';
        }

        if (!preg_match('/^[a-zA-Z0-9_\-\.\s]+$/', $name)) {
            $errors[] = 'Configuration name contains invalid characters (only letters, numbers, spaces, dots, hyphens, and underscores allowed)';
        }

        return $errors;
    }

    /**
     * Validate URL
     */
    private function validateUrl(mixed $url): array
    {
        $errors = [];

        if (!is_string($url)) {
            $errors[] = 'URL must be a string';
            return $errors;
        }

        if (empty(trim($url))) {
            $errors[] = 'URL cannot be empty';
            return $errors;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = 'URL format is invalid';
        }

        // Check for supported protocols
        $parsedUrl = parse_url($url);
        if ($parsedUrl === false || !isset($parsedUrl['scheme'])) {
            $errors[] = 'URL must include a protocol (http or https)';
        } elseif (!in_array(strtolower($parsedUrl['scheme']), ['http', 'https'])) {
            $errors[] = 'URL must use HTTP or HTTPS protocol';
        }

        return $errors;
    }

    /**
     * Validate HTTP method
     */
    private function validateMethod(mixed $method): array
    {
        $errors = [];

        if (!is_string($method)) {
            $errors[] = 'HTTP method must be a string';
            return $errors;
        }

        $validMethods = self::CONFIGURATION_SCHEMA['properties']['method']['enum'];
        if (!in_array(strtoupper($method), $validMethods)) {
            $errors[] = 'HTTP method must be one of: ' . implode(', ', $validMethods);
        }

        return $errors;
    }

    /**
     * Validate numeric field
     */
    private function validateNumericField(string $fieldName, mixed $value, int $min, int $max): array
    {
        $errors = [];

        if (!is_int($value)) {
            $errors[] = "{$fieldName} must be an integer";
            return $errors;
        }

        if ($value < $min || $value > $max) {
            $errors[] = "{$fieldName} must be between {$min} and {$max}";
        }

        return $errors;
    }

    /**
     * Validate headers array
     */
    private function validateHeaders(mixed $headers): array
    {
        $errors = [];

        if (!is_array($headers)) {
            $errors[] = 'Headers must be an array';
            return $errors;
        }

        foreach ($headers as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                $errors[] = 'Headers must be key-value pairs of strings';
                break;
            }

            if (empty(trim($key))) {
                $errors[] = 'Header names cannot be empty';
                break;
            }

            // Validate header name format (basic HTTP header validation)
            if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $key)) {
                $errors[] = "Invalid header name '{$key}' (only letters, numbers, hyphens, and underscores allowed)";
            }
        }

        return $errors;
    }

    /**
     * Validate request body
     */
    private function validateBody(mixed $body, string $method): array
    {
        $errors = [];

        if (!is_string($body)) {
            $errors[] = 'Request body must be a string';
            return $errors;
        }

        // If body is provided, validate JSON format for applicable methods
        if (!empty($body)) {
            $methodsWithBody = ['POST', 'PUT', 'PATCH'];
            if (in_array(strtoupper($method), $methodsWithBody)) {
                // Try to parse as JSON if it looks like JSON
                $trimmedBody = trim($body);
                if (str_starts_with($trimmedBody, '{') || str_starts_with($trimmedBody, '[')) {
                    json_decode($body);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $errors[] = 'Request body appears to be JSON but is invalid: ' . json_last_error_msg();
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Validate timestamp format
     */
    private function validateTimestamp(string $fieldName, mixed $timestamp): array
    {
        $errors = [];

        if (!is_string($timestamp)) {
            $errors[] = "{$fieldName} must be a string";
            return $errors;
        }

        // Try to parse the timestamp
        $dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $timestamp);
        if ($dateTime === false) {
            $errors[] = "{$fieldName} must be in format 'Y-m-d H:i:s'";
        }

        return $errors;
    }

    /**
     * Get human-readable JSON error message
     */
    private function getJsonErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
            JSON_ERROR_CTRL_CHAR => 'Control character error',
            JSON_ERROR_SYNTAX => 'Syntax error',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters',
            JSON_ERROR_RECURSION => 'Recursion detected',
            JSON_ERROR_INF_OR_NAN => 'Infinity or NaN value',
            JSON_ERROR_UNSUPPORTED_TYPE => 'Unsupported type',
            default => 'Unknown JSON error'
        };
    }

    /**
     * Get the JSON schema for configuration files
     * 
     * @return array JSON schema array
     */
    public function getConfigurationSchema(): array
    {
        return self::CONFIGURATION_SCHEMA;
    }

    /**
     * Validate configuration file and provide detailed error report
     * 
     * @param string $filePath Path to configuration file
     * @return array Validation result with errors and warnings
     */
    public function validateConfigurationFile(string $filePath): array
    {
        $result = [
            'isValid' => false,
            'errors' => [],
            'warnings' => [],
            'filePath' => $filePath
        ];

        try {
            // Check if file exists and is readable
            if (!file_exists($filePath)) {
                $result['errors'][] = 'Configuration file does not exist';
                return $result;
            }

            if (!is_readable($filePath)) {
                $result['errors'][] = 'Configuration file is not readable';
                return $result;
            }

            // Read and validate file content
            $content = file_get_contents($filePath);
            if ($content === false) {
                $result['errors'][] = 'Failed to read configuration file';
                return $result;
            }

            // Validate JSON content
            $errors = $this->validateJsonContent($content);
            $result['errors'] = $errors;

            // Add warnings for potential issues
            $warnings = $this->generateWarnings($content);
            $result['warnings'] = $warnings;

            $result['isValid'] = empty($errors);

        } catch (Exception $e) {
            $result['errors'][] = 'Validation error: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Generate warnings for potential configuration issues
     */
    private function generateWarnings(string $content): array
    {
        $warnings = [];

        try {
            $data = json_decode($content, true);
            if ($data === null) {
                return $warnings;
            }

            // Warning for very high concurrent connections
            if (isset($data['concurrentConnections']) && $data['concurrentConnections'] > 100) {
                $warnings[] = 'High concurrent connections may cause excessive server load';
            }

            // Warning for very long duration
            if (isset($data['duration']) && $data['duration'] > 300) {
                $warnings[] = 'Long test duration may consume significant resources';
            }

            // Warning for missing optional fields
            if (!isset($data['headers']) || empty($data['headers'])) {
                $warnings[] = 'No custom headers specified';
            }

            // Warning for localhost URLs in production-like configs
            if (isset($data['url']) && (str_contains($data['url'], 'localhost') || str_contains($data['url'], '127.0.0.1'))) {
                $warnings[] = 'Configuration uses localhost URL';
            }

        } catch (Exception $e) {
            // Ignore warnings generation errors
        }

        return $warnings;
    }
}