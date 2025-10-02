<?php

namespace OhaGui\Core;

use DateTime;
use InvalidArgumentException;

/**
 * Configuration Validator class for validating configuration file structure and content
 * Defines JSON schema and validation rules for configuration files
 */
class ConfigurationValidator
{
    /**
     * JSON schema definition for configuration files
     * This defines the expected structure and validation rules
     */
    private const CONFIGURATION_SCHEMA = [
        'type' => 'object',
        'required' => ['name', 'url', 'method'],
        'properties' => [
            'name' => [
                'type' => 'string',
                'minLength' => 1,
                'maxLength' => 100,
                'pattern' => '^[a-zA-Z0-9_\-\s]+$'
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
                'maximum' => 1000,
                'default' => 1
            ],
            'duration' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 3600,
                'default' => 2
            ],
            'timeout' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 300,
                'default' => 30
            ],
            'headers' => [
                'type' => 'object',
                'patternProperties' => [
                    '^.+$' => [
                        'type' => 'string'
                    ]
                ],
                'default' => []
            ],
            'body' => [
                'type' => 'string',
                'default' => ''
            ],
            'createdAt' => [
                'type' => 'string',
                'format' => 'date-time'
            ],
            'updatedAt' => [
                'type' => 'string',
                'format' => 'date-time'
            ]
        ]
    ];

    /**
     * Validate configuration data against the schema
     * 
     * @param array $data Configuration data to validate
     * @return array Array of validation errors (empty if valid)
     */
    public function validateConfiguration(array $data): array
    {
        $errors = [];

        // Check required fields
        $errors = array_merge($errors, $this->validateRequiredFields($data));

        // Validate individual fields
        $errors = array_merge($errors, $this->validateName($data));
        $errors = array_merge($errors, $this->validateUrl($data));
        $errors = array_merge($errors, $this->validateMethod($data));
        $errors = array_merge($errors, $this->validateConcurrentConnections($data));
        $errors = array_merge($errors, $this->validateDuration($data));
        $errors = array_merge($errors, $this->validateTimeout($data));
        $errors = array_merge($errors, $this->validateHeaders($data));
        $errors = array_merge($errors, $this->validateBody($data));
        $errors = array_merge($errors, $this->validateTimestamps($data));

        return array_unique($errors);
    }

    /**
     * Validate that required fields are present
     * 
     * @param array $data
     * @return array
     */
    private function validateRequiredFields(array $data): array
    {
        $errors = [];
        $required = self::CONFIGURATION_SCHEMA['required'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $errors[] = "Required field '{$field}' is missing or empty";
            }
        }

        return $errors;
    }

    /**
     * Validate configuration name
     * 
     * @param array $data
     * @return array
     */
    private function validateName(array $data): array
    {
        $errors = [];
        
        if (!isset($data['name'])) {
            return $errors; // Already handled in required fields
        }

        $name = $data['name'];
        $schema = self::CONFIGURATION_SCHEMA['properties']['name'];

        if (!is_string($name)) {
            $errors[] = 'Configuration name must be a string';
            return $errors;
        }

        if (strlen($name) < $schema['minLength']) {
            $errors[] = "Configuration name must be at least {$schema['minLength']} character(s) long";
        }

        if (strlen($name) > $schema['maxLength']) {
            $errors[] = "Configuration name must be no more than {$schema['maxLength']} characters long";
        }

        if (!preg_match('/' . $schema['pattern'] . '/', $name)) {
            $errors[] = 'Configuration name can only contain letters, numbers, spaces, hyphens, and underscores';
        }

        return $errors;
    }

    /**
     * Validate URL
     * 
     * @param array $data
     * @return array
     */
    private function validateUrl(array $data): array
    {
        $errors = [];
        
        if (!isset($data['url'])) {
            return $errors; // Already handled in required fields
        }

        $url = $data['url'];

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

        // Additional URL validation
        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !isset($parsedUrl['scheme']) || !isset($parsedUrl['host'])) {
            $errors[] = 'URL must include scheme (http/https) and host';
        }

        if (isset($parsedUrl['scheme']) && !in_array($parsedUrl['scheme'], ['http', 'https'])) {
            $errors[] = 'URL scheme must be http or https';
        }

        return $errors;
    }

    /**
     * Validate HTTP method
     * 
     * @param array $data
     * @return array
     */
    private function validateMethod(array $data): array
    {
        $errors = [];
        
        if (!isset($data['method'])) {
            return $errors; // Already handled in required fields
        }

        $method = $data['method'];
        $validMethods = self::CONFIGURATION_SCHEMA['properties']['method']['enum'];

        if (!is_string($method)) {
            $errors[] = 'HTTP method must be a string';
            return $errors;
        }

        $method = strtoupper(trim($method));
        if (!in_array($method, $validMethods)) {
            $errors[] = 'HTTP method must be one of: ' . implode(', ', $validMethods);
        }

        return $errors;
    }

    /**
     * Validate concurrent connections
     * 
     * @param array $data
     * @return array
     */
    private function validateConcurrentConnections(array $data): array
    {
        $errors = [];
        
        if (!isset($data['concurrentConnections'])) {
            return $errors; // Optional field
        }

        $connections = $data['concurrentConnections'];
        $schema = self::CONFIGURATION_SCHEMA['properties']['concurrentConnections'];

        if (!is_int($connections) && !is_numeric($connections)) {
            $errors[] = 'Concurrent connections must be a number';
            return $errors;
        }

        $connections = (int)$connections;

        if ($connections < $schema['minimum']) {
            $errors[] = "Concurrent connections must be at least {$schema['minimum']}";
        }

        if ($connections > $schema['maximum']) {
            $errors[] = "Concurrent connections must be no more than {$schema['maximum']}";
        }

        return $errors;
    }

    /**
     * Validate duration
     * 
     * @param array $data
     * @return array
     */
    private function validateDuration(array $data): array
    {
        $errors = [];
        
        if (!isset($data['duration'])) {
            return $errors; // Optional field
        }

        $duration = $data['duration'];
        $schema = self::CONFIGURATION_SCHEMA['properties']['duration'];

        if (!is_int($duration) && !is_numeric($duration)) {
            $errors[] = 'Duration must be a number';
            return $errors;
        }

        $duration = (int)$duration;

        if ($duration < $schema['minimum']) {
            $errors[] = "Duration must be at least {$schema['minimum']} second(s)";
        }

        if ($duration > $schema['maximum']) {
            $errors[] = "Duration must be no more than {$schema['maximum']} seconds";
        }

        return $errors;
    }

    /**
     * Validate timeout
     * 
     * @param array $data
     * @return array
     */
    private function validateTimeout(array $data): array
    {
        $errors = [];
        
        if (!isset($data['timeout'])) {
            return $errors; // Optional field
        }

        $timeout = $data['timeout'];
        $schema = self::CONFIGURATION_SCHEMA['properties']['timeout'];

        if (!is_int($timeout) && !is_numeric($timeout)) {
            $errors[] = 'Timeout must be a number';
            return $errors;
        }

        $timeout = (int)$timeout;

        if ($timeout < $schema['minimum']) {
            $errors[] = "Timeout must be at least {$schema['minimum']} second(s)";
        }

        if ($timeout > $schema['maximum']) {
            $errors[] = "Timeout must be no more than {$schema['maximum']} seconds";
        }

        return $errors;
    }

    /**
     * Validate headers
     * 
     * @param array $data
     * @return array
     */
    private function validateHeaders(array $data): array
    {
        $errors = [];
        
        if (!isset($data['headers'])) {
            return $errors; // Optional field
        }

        $headers = $data['headers'];

        if (!is_array($headers)) {
            $errors[] = 'Headers must be an array/object';
            return $errors;
        }

        foreach ($headers as $key => $value) {
            if (!is_string($key) || empty(trim($key))) {
                $errors[] = 'Header keys must be non-empty strings';
                break;
            }

            if (!is_string($value)) {
                $errors[] = 'Header values must be strings';
                break;
            }
        }

        return $errors;
    }

    /**
     * Validate request body
     * 
     * @param array $data
     * @return array
     */
    private function validateBody(array $data): array
    {
        $errors = [];
        
        if (!isset($data['body'])) {
            return $errors; // Optional field
        }

        $body = $data['body'];

        if (!is_string($body)) {
            $errors[] = 'Request body must be a string';
            return $errors;
        }

        // If body is not empty and method supports body, validate JSON format
        if (!empty(trim($body)) && isset($data['method'])) {
            $method = strtoupper($data['method']);
            $methodsWithBody = ['POST', 'PUT', 'PATCH'];
            
            if (in_array($method, $methodsWithBody)) {
                if (!$this->isValidJson($body) && !$this->isValidFormData($body)) {
                    $errors[] = 'Request body must be valid JSON or form data for ' . $method . ' requests';
                }
            }
        }

        return $errors;
    }

    /**
     * Validate timestamps
     * 
     * @param array $data
     * @return array
     */
    private function validateTimestamps(array $data): array
    {
        $errors = [];
        
        $timestampFields = ['createdAt', 'updatedAt'];
        
        foreach ($timestampFields as $field) {
            if (isset($data[$field])) {
                $timestamp = $data[$field];
                
                if (!is_string($timestamp)) {
                    $errors[] = "{$field} must be a string";
                    continue;
                }

                // Try to parse the timestamp
                $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp);
                if (!$dateTime || $dateTime->format('Y-m-d H:i:s') !== $timestamp) {
                    $errors[] = "{$field} must be in format 'Y-m-d H:i:s'";
                }
            }
        }

        return $errors;
    }

    /**
     * Check if string is valid JSON
     * 
     * @param string $string
     * @return bool
     */
    private function isValidJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Check if string is valid form data format
     * 
     * @param string $string
     * @return bool
     */
    private function isValidFormData(string $string): bool
    {
        // Form data must contain at least one = sign for key=value pairs
        if (!str_contains($string, '=')) {
            return false;
        }
        
        // Simple check for form data format (key=value&key=value)
        return preg_match('/^[^=&]+(=[^&]*)?(&[^=&]+(=[^&]*)?)*$/', $string) === 1;
    }

    /**
     * Validate configuration file content (JSON string)
     * 
     * @param string $jsonContent JSON content to validate
     * @return array Array of validation errors
     */
    public function validateConfigurationFile(string $jsonContent): array
    {
        $errors = [];

        // First, validate JSON syntax
        $data = json_decode($jsonContent, true);
        
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = 'Invalid JSON format: ' . json_last_error_msg();
            return $errors;
        }

        if (!is_array($data) || array_keys($data) === range(0, count($data) - 1)) {
            $errors[] = 'Configuration file must contain a JSON object';
            return $errors;
        }

        // Then validate configuration structure and content
        return array_merge($errors, $this->validateConfiguration($data));
    }

    /**
     * Get the configuration schema
     * 
     * @return array
     */
    public function getSchema(): array
    {
        return self::CONFIGURATION_SCHEMA;
    }

    /**
     * Get default values for optional fields
     * 
     * @return array
     */
    public function getDefaultValues(): array
    {
        $defaults = [];
        
        foreach (self::CONFIGURATION_SCHEMA['properties'] as $field => $schema) {
            if (isset($schema['default'])) {
                $defaults[$field] = $schema['default'];
            }
        }

        return $defaults;
    }

    /**
     * Sanitize and normalize configuration data
     * 
     * @param array $data Raw configuration data
     * @return array Sanitized configuration data
     */
    public function sanitizeConfiguration(array $data): array
    {
        $defaults = $this->getDefaultValues();

        // Apply defaults for missing optional fields
        $sanitized = array_merge($defaults, $data);

        // Sanitize individual fields
        if (isset($sanitized['name'])) {
            $sanitized['name'] = trim($sanitized['name']);
        }

        if (isset($sanitized['url'])) {
            $sanitized['url'] = trim($sanitized['url']);
        }

        if (isset($sanitized['method'])) {
            $sanitized['method'] = strtoupper(trim($sanitized['method']));
        }

        if (isset($sanitized['concurrentConnections'])) {
            $sanitized['concurrentConnections'] = (int)$sanitized['concurrentConnections'];
        }

        if (isset($sanitized['duration'])) {
            $sanitized['duration'] = (int)$sanitized['duration'];
        }

        if (isset($sanitized['timeout'])) {
            $sanitized['timeout'] = (int)$sanitized['timeout'];
        }

        if (isset($sanitized['headers']) && !is_array($sanitized['headers'])) {
            $sanitized['headers'] = [];
        }

        if (isset($sanitized['body'])) {
            $sanitized['body'] = (string)$sanitized['body'];
        }

        return $sanitized;
    }

    /**
     * Check if configuration data is valid
     * 
     * @param array $data Configuration data
     * @return bool True if valid
     */
    public function isValid(array $data): bool
    {
        return empty($this->validateConfiguration($data));
    }

    /**
     * Validate and throw exception if invalid
     * 
     * @param array $data Configuration data
     * @throws InvalidArgumentException If validation fails
     */
    public function validateOrThrow(array $data): void
    {
        $errors = $this->validateConfiguration($data);
        
        if (!empty($errors)) {
            throw new InvalidArgumentException('Configuration validation failed: ' . implode(', ', $errors));
        }
    }
}