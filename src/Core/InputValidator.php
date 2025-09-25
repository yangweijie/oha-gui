<?php

namespace OhaGui\Core;

use OhaGui\Models\TestConfiguration;

/**
 * Comprehensive input validator for GUI form fields
 * Provides real-time validation with detailed user feedback
 */
class InputValidator
{
    /**
     * Validation result structure
     */
    public const VALIDATION_RESULT = [
        'isValid' => false,
        'errors' => [],
        'warnings' => [],
        'fieldErrors' => []
    ];

    /**
     * Validate URL field with detailed feedback
     * 
     * @param string $url URL to validate
     * @return array Validation result
     */
    public function validateUrl(string $url): array
    {
        $result = self::VALIDATION_RESULT;
        $result['isValid'] = true;

        // Check if URL is empty
        if (empty(trim($url))) {
            $result['isValid'] = false;
            $result['errors'][] = 'URL is required';
            $result['fieldErrors']['url'] = 'URL cannot be empty';
            return $result;
        }

        // Basic URL format validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $result['isValid'] = false;
            $result['errors'][] = 'Invalid URL format';
            $result['fieldErrors']['url'] = 'Please enter a valid URL (e.g., http://example.com)';
            return $result;
        }

        // Parse URL for detailed validation
        $parsedUrl = parse_url($url);
        if ($parsedUrl === false) {
            $result['isValid'] = false;
            $result['errors'][] = 'URL cannot be parsed';
            $result['fieldErrors']['url'] = 'URL format is malformed';
            return $result;
        }

        // Check for required scheme
        if (!isset($parsedUrl['scheme'])) {
            $result['isValid'] = false;
            $result['errors'][] = 'URL must include protocol';
            $result['fieldErrors']['url'] = 'URL must start with http:// or https://';
            return $result;
        }

        // Validate supported protocols
        $supportedSchemes = ['http', 'https'];
        if (!in_array(strtolower($parsedUrl['scheme']), $supportedSchemes)) {
            $result['isValid'] = false;
            $result['errors'][] = 'Unsupported protocol';
            $result['fieldErrors']['url'] = 'Only HTTP and HTTPS protocols are supported';
            return $result;
        }

        // Check for host
        if (!isset($parsedUrl['host']) || empty($parsedUrl['host'])) {
            $result['isValid'] = false;
            $result['errors'][] = 'URL must include hostname';
            $result['fieldErrors']['url'] = 'URL must include a valid hostname';
            return $result;
        }

        // Add warnings for common issues
        if (strtolower($parsedUrl['scheme']) === 'http') {
            $result['warnings'][] = 'Using HTTP instead of HTTPS may expose data';
        }

        if (in_array($parsedUrl['host'], ['localhost', '127.0.0.1', '::1'])) {
            $result['warnings'][] = 'Testing against localhost';
        }

        return $result;
    }

    /**
     * Validate HTTP method
     * 
     * @param string $method HTTP method to validate
     * @return array Validation result
     */
    public function validateHttpMethod(string $method): array
    {
        $result = self::VALIDATION_RESULT;
        $result['isValid'] = true;

        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        $method = strtoupper(trim($method));

        if (empty($method)) {
            $result['isValid'] = false;
            $result['errors'][] = 'HTTP method is required';
            $result['fieldErrors']['method'] = 'Please select an HTTP method';
            return $result;
        }

        if (!in_array($method, $validMethods)) {
            $result['isValid'] = false;
            $result['errors'][] = 'Invalid HTTP method';
            $result['fieldErrors']['method'] = 'Method must be one of: ' . implode(', ', $validMethods);
            return $result;
        }

        return $result;
    }

    /**
     * Validate numeric field with range checking
     * 
     * @param string $fieldName Field name for error messages
     * @param mixed $value Value to validate
     * @param int $min Minimum allowed value
     * @param int $max Maximum allowed value
     * @param string $unit Unit for display (e.g., 'seconds', 'connections')
     * @return array Validation result
     */
    public function validateNumericField(string $fieldName, mixed $value, int $min, int $max, string $unit = ''): array
    {
        $result = self::VALIDATION_RESULT;
        $result['isValid'] = true;

        // Check if value is numeric
        if (!is_numeric($value)) {
            $result['isValid'] = false;
            $result['errors'][] = "{$fieldName} must be a number";
            $result['fieldErrors'][strtolower($fieldName)] = "Please enter a valid number";
            return $result;
        }

        $numValue = (int)$value;

        // Check range
        if ($numValue < $min || $numValue > $max) {
            $result['isValid'] = false;
            $unitText = $unit ? " {$unit}" : '';
            $result['errors'][] = "{$fieldName} must be between {$min} and {$max}{$unitText}";
            $result['fieldErrors'][strtolower($fieldName)] = "Value must be between {$min} and {$max}{$unitText}";
            return $result;
        }

        // Add warnings for potentially problematic values
        $this->addNumericWarnings($result, $fieldName, $numValue, $min, $max, $unit);

        return $result;
    }

    /**
     * Add warnings for numeric fields
     */
    private function addNumericWarnings(array &$result, string $fieldName, int $value, int $min, int $max, string $unit): void
    {
        $fieldLower = strtolower($fieldName);

        // Warnings for concurrent connections
        if ($fieldLower === 'concurrent connections') {
            if ($value > 100) {
                $result['warnings'][] = 'High concurrent connections may cause excessive server load';
            }
            if ($value === 1) {
                $result['warnings'][] = 'Single connection may not provide meaningful load test results';
            }
        }

        // Warnings for duration
        if ($fieldLower === 'duration') {
            if ($value > 300) {
                $result['warnings'][] = 'Long test duration may consume significant resources';
            }
            if ($value < 5) {
                $result['warnings'][] = 'Very short duration may not provide stable results';
            }
        }

        // Warnings for timeout
        if ($fieldLower === 'timeout') {
            if ($value > 60) {
                $result['warnings'][] = 'High timeout value may mask performance issues';
            }
        }
    }

    /**
     * Validate request headers
     * 
     * @param string $headersText Headers in text format (key: value per line)
     * @return array Validation result
     */
    public function validateHeaders(string $headersText): array
    {
        $result = self::VALIDATION_RESULT;
        $result['isValid'] = true;

        if (empty(trim($headersText))) {
            $result['warnings'][] = 'No custom headers specified';
            return $result;
        }

        $lines = explode("\n", $headersText);
        $lineNumber = 0;
        $headerNames = [];

        foreach ($lines as $line) {
            $lineNumber++;
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Check header format (key: value)
            if (!str_contains($line, ':')) {
                $result['isValid'] = false;
                $result['errors'][] = "Invalid header format on line {$lineNumber}";
                $result['fieldErrors']['headers'] = "Line {$lineNumber}: Headers must be in 'Name: Value' format";
                continue;
            }

            $parts = explode(':', $line, 2);
            $headerName = trim($parts[0]);
            $headerValue = trim($parts[1]);

            // Validate header name
            if (empty($headerName)) {
                $result['isValid'] = false;
                $result['errors'][] = "Empty header name on line {$lineNumber}";
                $result['fieldErrors']['headers'] = "Line {$lineNumber}: Header name cannot be empty";
                continue;
            }

            // Check for valid header name characters
            if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $headerName)) {
                $result['isValid'] = false;
                $result['errors'][] = "Invalid header name '{$headerName}' on line {$lineNumber}";
                $result['fieldErrors']['headers'] = "Line {$lineNumber}: Header names can only contain letters, numbers, hyphens, and underscores";
                continue;
            }

            // Check for duplicate headers
            $headerNameLower = strtolower($headerName);
            if (in_array($headerNameLower, $headerNames)) {
                $result['warnings'][] = "Duplicate header '{$headerName}' on line {$lineNumber}";
            } else {
                $headerNames[] = $headerNameLower;
            }

            // Validate header value
            if (empty($headerValue)) {
                $result['warnings'][] = "Empty header value for '{$headerName}' on line {$lineNumber}";
            }

            // Check for potentially problematic headers
            $this->addHeaderWarnings($result, $headerName, $headerValue);
        }

        return $result;
    }

    /**
     * Add warnings for specific headers
     */
    private function addHeaderWarnings(array &$result, string $name, string $value): void
    {
        $nameLower = strtolower($name);

        // Warn about headers that might be overridden
        $problematicHeaders = ['host', 'content-length', 'connection', 'transfer-encoding'];
        if (in_array($nameLower, $problematicHeaders)) {
            $result['warnings'][] = "Header '{$name}' might be overridden by the HTTP client";
        }

        // Warn about authorization headers
        if (in_array($nameLower, ['authorization', 'cookie'])) {
            $result['warnings'][] = "Sensitive header '{$name}' detected - ensure this is intentional";
        }
    }

    /**
     * Validate request body
     * 
     * @param string $body Request body content
     * @param string $method HTTP method
     * @return array Validation result
     */
    public function validateRequestBody(string $body, string $method): array
    {
        $result = self::VALIDATION_RESULT;
        $result['isValid'] = true;

        $method = strtoupper($method);
        $methodsWithBody = ['POST', 'PUT', 'PATCH'];

        // Check if body is provided for methods that don't typically use it
        if (!empty(trim($body)) && !in_array($method, $methodsWithBody)) {
            $result['warnings'][] = "Request body provided for {$method} method (will be ignored)";
        }

        // If no body provided for methods that typically use it
        if (empty(trim($body)) && in_array($method, $methodsWithBody)) {
            $result['warnings'][] = "No request body provided for {$method} method";
            return $result;
        }

        // Skip validation if body is empty
        if (empty(trim($body))) {
            return $result;
        }

        // Validate JSON format if it looks like JSON
        $trimmedBody = trim($body);
        if ($this->looksLikeJson($trimmedBody)) {
            $jsonResult = $this->validateJsonContent($trimmedBody);
            if (!$jsonResult['isValid']) {
                $result['isValid'] = false;
                $result['errors'] = array_merge($result['errors'], $jsonResult['errors']);
                $result['fieldErrors']['body'] = 'Invalid JSON format';
            }
        }

        // Check body size
        $bodySize = strlen($body);
        if ($bodySize > 1024 * 1024) { // 1MB
            $result['warnings'][] = 'Large request body may impact performance';
        }

        return $result;
    }

    /**
     * Check if content looks like JSON
     */
    private function looksLikeJson(string $content): bool
    {
        $content = trim($content);
        return (str_starts_with($content, '{') && str_ends_with($content, '}')) ||
               (str_starts_with($content, '[') && str_ends_with($content, ']'));
    }

    /**
     * Validate JSON content
     */
    private function validateJsonContent(string $content): array
    {
        $result = self::VALIDATION_RESULT;
        $result['isValid'] = true;

        json_decode($content);
        $jsonError = json_last_error();

        if ($jsonError !== JSON_ERROR_NONE) {
            $result['isValid'] = false;
            $result['errors'][] = 'Invalid JSON: ' . json_last_error_msg();
        }

        return $result;
    }

    /**
     * Validate complete test configuration
     * 
     * @param TestConfiguration $config Configuration to validate
     * @return array Comprehensive validation result
     */
    public function validateConfiguration(TestConfiguration $config): array
    {
        $result = self::VALIDATION_RESULT;
        $result['isValid'] = true;

        // Validate URL
        $urlResult = $this->validateUrl($config->url);
        $this->mergeValidationResults($result, $urlResult);

        // Validate HTTP method
        $methodResult = $this->validateHttpMethod($config->method);
        $this->mergeValidationResults($result, $methodResult);

        // Validate numeric fields
        $numericFields = [
            ['Concurrent Connections', $config->concurrentConnections, 1, 1000, 'connections'],
            ['Duration', $config->duration, 1, 3600, 'seconds'],
            ['Timeout', $config->timeout, 1, 300, 'seconds']
        ];

        foreach ($numericFields as [$name, $value, $min, $max, $unit]) {
            $numericResult = $this->validateNumericField($name, $value, $min, $max, $unit);
            $this->mergeValidationResults($result, $numericResult);
        }

        // Validate headers
        $headersText = $this->formatHeadersForValidation($config->headers);
        $headersResult = $this->validateHeaders($headersText);
        $this->mergeValidationResults($result, $headersResult);

        // Validate request body
        $bodyResult = $this->validateRequestBody($config->body, $config->method);
        $this->mergeValidationResults($result, $bodyResult);

        // Business rule validations
        $businessResult = $this->validateBusinessRules($config);
        $this->mergeValidationResults($result, $businessResult);

        return $result;
    }

    /**
     * Validate business rules
     */
    private function validateBusinessRules(TestConfiguration $config): array
    {
        $result = self::VALIDATION_RESULT;
        $result['isValid'] = true;

        // Check timeout vs duration
        if ($config->timeout > $config->duration) {
            $result['isValid'] = false;
            $result['errors'][] = 'Timeout cannot be greater than test duration';
            $result['fieldErrors']['timeout'] = 'Timeout must be less than or equal to duration';
        }

        // Check for excessive load
        $totalRequests = $config->concurrentConnections * $config->duration;
        if ($totalRequests > 100000) {
            $result['warnings'][] = 'Configuration may generate excessive load - consider reducing parameters';
        }

        // Check for very low load
        if ($config->concurrentConnections === 1 && $config->duration < 10) {
            $result['warnings'][] = 'Very light load configuration may not provide meaningful results';
        }

        return $result;
    }

    /**
     * Format headers array for validation
     */
    private function formatHeadersForValidation(array $headers): string
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }
        return implode("\n", $lines);
    }

    /**
     * Merge validation results
     */
    private function mergeValidationResults(array &$target, array $source): void
    {
        if (!$source['isValid']) {
            $target['isValid'] = false;
        }

        $target['errors'] = array_merge($target['errors'], $source['errors']);
        $target['warnings'] = array_merge($target['warnings'], $source['warnings']);
        $target['fieldErrors'] = array_merge($target['fieldErrors'], $source['fieldErrors']);
    }

    /**
     * Get user-friendly validation summary
     * 
     * @param array $validationResult Validation result from validateConfiguration
     * @return string Human-readable summary
     */
    public function getValidationSummary(array $validationResult): string
    {
        if ($validationResult['isValid']) {
            $summary = '✓ Configuration is valid';
            
            if (!empty($validationResult['warnings'])) {
                $warningCount = count($validationResult['warnings']);
                $summary .= " ({$warningCount} warning" . ($warningCount > 1 ? 's' : '') . ')';
            }
            
            return $summary;
        }

        $errorCount = count($validationResult['errors']);
        $summary = "⚠ {$errorCount} error" . ($errorCount > 1 ? 's' : '') . ' found';

        if (!empty($validationResult['warnings'])) {
            $warningCount = count($validationResult['warnings']);
            $summary .= ", {$warningCount} warning" . ($warningCount > 1 ? 's' : '');
        }

        return $summary;
    }

    /**
     * Get detailed validation message for display
     * 
     * @param array $validationResult Validation result
     * @return string Detailed message
     */
    public function getDetailedValidationMessage(array $validationResult): string
    {
        $messages = [];

        if (!empty($validationResult['errors'])) {
            $messages[] = 'Errors:';
            foreach ($validationResult['errors'] as $error) {
                $messages[] = '• ' . $error;
            }
        }

        if (!empty($validationResult['warnings'])) {
            if (!empty($messages)) {
                $messages[] = '';
            }
            $messages[] = 'Warnings:';
            foreach ($validationResult['warnings'] as $warning) {
                $messages[] = '• ' . $warning;
            }
        }

        return implode("\n", $messages);
    }
}