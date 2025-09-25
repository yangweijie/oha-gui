<?php

use OhaGui\Core\ConfigurationValidator;

/**
 * Unit tests for ConfigurationValidator class
 */
class ConfigurationValidatorTest
{
    private ConfigurationValidator $validator;

    public function setUp(): void
    {
        $this->validator = new ConfigurationValidator();
    }

    /**
     * Test validating valid configuration data
     */
    public function testValidateValidConfigurationData(): void
    {
        $validData = [
            'name' => 'test-config',
            'url' => 'https://api.example.com',
            'method' => 'GET',
            'concurrentConnections' => 10,
            'duration' => 30,
            'timeout' => 10,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => '',
            'createdAt' => '2024-01-01 12:00:00',
            'updatedAt' => '2024-01-01 12:00:00'
        ];

        $errors = $this->validator->validateConfigurationData($validData);
        
        $this->assertEmpty($errors);
    }

    /**
     * Test validating configuration with missing required fields
     */
    public function testValidateMissingRequiredFields(): void
    {
        $invalidData = [
            'name' => 'test-config',
            // Missing url, method, concurrentConnections, duration, timeout
        ];

        $errors = $this->validator->validateConfigurationData($invalidData);
        
        $this->assertNotEmpty($errors);
        $this->assertContains("Required field 'url' is missing", $errors);
        $this->assertContains("Required field 'method' is missing", $errors);
        $this->assertContains("Required field 'concurrentConnections' is missing", $errors);
        $this->assertContains("Required field 'duration' is missing", $errors);
        $this->assertContains("Required field 'timeout' is missing", $errors);
    }

    /**
     * Test validating configuration with invalid URL
     */
    public function testValidateInvalidUrl(): void
    {
        $invalidData = [
            'name' => 'test-config',
            'url' => 'not-a-valid-url',
            'method' => 'GET',
            'concurrentConnections' => 10,
            'duration' => 30,
            'timeout' => 10
        ];

        $errors = $this->validator->validateConfigurationData($invalidData);
        
        $this->assertNotEmpty($errors);
        $this->assertContains('URL format is invalid', $errors);
    }

    /**
     * Test validating configuration with invalid HTTP method
     */
    public function testValidateInvalidMethod(): void
    {
        $invalidData = [
            'name' => 'test-config',
            'url' => 'https://example.com',
            'method' => 'INVALID',
            'concurrentConnections' => 10,
            'duration' => 30,
            'timeout' => 10
        ];

        $errors = $this->validator->validateConfigurationData($invalidData);
        
        $this->assertNotEmpty($errors);
        $this->assertContains('HTTP method must be one of: GET, POST, PUT, DELETE, PATCH', $errors);
    }

    /**
     * Test validating configuration with out-of-range numeric values
     */
    public function testValidateOutOfRangeValues(): void
    {
        $invalidData = [
            'name' => 'test-config',
            'url' => 'https://example.com',
            'method' => 'GET',
            'concurrentConnections' => 2000,  // Too high
            'duration' => 0,  // Too low
            'timeout' => 500  // Too high
        ];

        $errors = $this->validator->validateConfigurationData($invalidData);
        
        $this->assertNotEmpty($errors);
        $this->assertContains('concurrentConnections must be between 1 and 1000', $errors);
        $this->assertContains('duration must be between 1 and 3600', $errors);
        $this->assertContains('timeout must be between 1 and 300', $errors);
    }

    /**
     * Test validating configuration with invalid headers
     */
    public function testValidateInvalidHeaders(): void
    {
        $invalidData = [
            'name' => 'test-config',
            'url' => 'https://example.com',
            'method' => 'GET',
            'concurrentConnections' => 10,
            'duration' => 30,
            'timeout' => 10,
            'headers' => [
                '' => 'value',  // Empty header name
                'valid-header' => 'value'
            ]
        ];

        $errors = $this->validator->validateConfigurationData($invalidData);
        
        $this->assertNotEmpty($errors);
        $this->assertContains('Header names cannot be empty', $errors);
    }

    /**
     * Test validating configuration with invalid JSON body
     */
    public function testValidateInvalidJsonBody(): void
    {
        $invalidData = [
            'name' => 'test-config',
            'url' => 'https://example.com',
            'method' => 'POST',
            'concurrentConnections' => 10,
            'duration' => 30,
            'timeout' => 10,
            'body' => '{"invalid": json}'  // Invalid JSON
        ];

        $errors = $this->validator->validateConfigurationData($invalidData);
        
        $this->assertNotEmpty($errors);
        $this->assertTrue(
            array_filter($errors, fn($error) => str_contains($error, 'Request body appears to be JSON but is invalid'))
        );
    }

    /**
     * Test business rule validation - body with GET method
     */
    public function testValidateBodyWithGetMethod(): void
    {
        $invalidData = [
            'name' => 'test-config',
            'url' => 'https://example.com',
            'method' => 'GET',
            'concurrentConnections' => 10,
            'duration' => 30,
            'timeout' => 10,
            'body' => '{"should": "not be here"}'
        ];

        $errors = $this->validator->validateConfigurationData($invalidData);
        
        $this->assertNotEmpty($errors);
        $this->assertContains('Request body is not allowed for GET method', $errors);
    }

    /**
     * Test business rule validation - timeout greater than duration
     */
    public function testValidateTimeoutGreaterThanDuration(): void
    {
        $invalidData = [
            'name' => 'test-config',
            'url' => 'https://example.com',
            'method' => 'GET',
            'concurrentConnections' => 10,
            'duration' => 10,
            'timeout' => 20  // Greater than duration
        ];

        $errors = $this->validator->validateConfigurationData($invalidData);
        
        $this->assertNotEmpty($errors);
        $this->assertContains('Timeout cannot be greater than test duration', $errors);
    }

    /**
     * Test validating valid JSON content
     */
    public function testValidateValidJsonContent(): void
    {
        $validJson = json_encode([
            'name' => 'test-config',
            'url' => 'https://example.com',
            'method' => 'GET',
            'concurrentConnections' => 10,
            'duration' => 30,
            'timeout' => 10
        ]);

        $errors = $this->validator->validateJsonContent($validJson);
        
        $this->assertEmpty($errors);
    }

    /**
     * Test validating empty JSON content
     */
    public function testValidateEmptyJsonContent(): void
    {
        $errors = $this->validator->validateJsonContent('');
        
        $this->assertNotEmpty($errors);
        $this->assertContains('Configuration content is empty', $errors);
    }

    /**
     * Test validating malformed JSON content
     */
    public function testValidateMalformedJsonContent(): void
    {
        $malformedJson = '{"name": "test", "url": "https://example.com"'; // Missing closing brace
        
        $errors = $this->validator->validateJsonContent($malformedJson);
        
        $this->assertNotEmpty($errors);
        $this->assertTrue(
            array_filter($errors, fn($error) => str_contains($error, 'Invalid JSON syntax'))
        );
    }

    /**
     * Test validating non-object JSON content
     */
    public function testValidateNonObjectJsonContent(): void
    {
        $arrayJson = '["not", "an", "object"]';
        
        $errors = $this->validator->validateJsonContent($arrayJson);
        
        $this->assertNotEmpty($errors);
        $this->assertContains('Configuration must be a JSON object', $errors);
    }

    /**
     * Test getting configuration schema
     */
    public function testGetConfigurationSchema(): void
    {
        $schema = $this->validator->getConfigurationSchema();
        
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('type', $schema);
        $this->assertArrayHasKey('required', $schema);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertEquals('object', $schema['type']);
        
        $requiredFields = $schema['required'];
        $this->assertContains('name', $requiredFields);
        $this->assertContains('url', $requiredFields);
        $this->assertContains('method', $requiredFields);
        $this->assertContains('concurrentConnections', $requiredFields);
        $this->assertContains('duration', $requiredFields);
        $this->assertContains('timeout', $requiredFields);
    }

    /**
     * Test validating configuration file
     */
    public function testValidateConfigurationFile(): void
    {
        // Create a temporary valid configuration file
        $tempFile = tempnam(sys_get_temp_dir(), 'oha_test_config');
        $validConfig = [
            'name' => 'test-config',
            'url' => 'https://example.com',
            'method' => 'GET',
            'concurrentConnections' => 10,
            'duration' => 30,
            'timeout' => 10
        ];
        file_put_contents($tempFile, json_encode($validConfig));

        $result = $this->validator->validateConfigurationFile($tempFile);
        
        $this->assertTrue($result['isValid']);
        $this->assertEmpty($result['errors']);
        $this->assertIsArray($result['warnings']);
        $this->assertEquals($tempFile, $result['filePath']);

        // Clean up
        unlink($tempFile);
    }

    /**
     * Test validating non-existent configuration file
     */
    public function testValidateNonExistentConfigurationFile(): void
    {
        $result = $this->validator->validateConfigurationFile('/non/existent/file.json');
        
        $this->assertFalse($result['isValid']);
        $this->assertContains('Configuration file does not exist', $result['errors']);
    }

    /**
     * Test validation with warnings
     */
    public function testValidationWithWarnings(): void
    {
        // Create a configuration that should generate warnings
        $tempFile = tempnam(sys_get_temp_dir(), 'oha_test_config');
        $configWithWarnings = [
            'name' => 'test-config',
            'url' => 'http://localhost:8080',  // Should generate localhost warning
            'method' => 'GET',
            'concurrentConnections' => 200,  // Should generate high connections warning
            'duration' => 600,  // Should generate long duration warning
            'timeout' => 10
        ];
        file_put_contents($tempFile, json_encode($configWithWarnings));

        $result = $this->validator->validateConfigurationFile($tempFile);
        
        $this->assertTrue($result['isValid']);
        $this->assertEmpty($result['errors']);
        $this->assertNotEmpty($result['warnings']);
        
        $warnings = $result['warnings'];
        $this->assertContains('High concurrent connections may cause excessive server load', $warnings);
        $this->assertContains('Long test duration may consume significant resources', $warnings);
        $this->assertContains('Configuration uses localhost URL', $warnings);

        // Clean up
        unlink($tempFile);
    }

    /**
     * Test validation of configuration name with invalid characters
     */
    public function testValidateInvalidConfigurationName(): void
    {
        $invalidData = [
            'name' => 'test/config\\with:invalid*chars',
            'url' => 'https://example.com',
            'method' => 'GET',
            'concurrentConnections' => 10,
            'duration' => 30,
            'timeout' => 10
        ];

        $errors = $this->validator->validateConfigurationData($invalidData);
        
        $this->assertNotEmpty($errors);
        $this->assertTrue(
            array_filter($errors, fn($error) => str_contains($error, 'Configuration name contains invalid characters'))
        );
    }

    /**
     * Test validation of URL with unsupported protocol
     */
    public function testValidateUnsupportedProtocol(): void
    {
        $invalidData = [
            'name' => 'test-config',
            'url' => 'ftp://example.com',
            'method' => 'GET',
            'concurrentConnections' => 10,
            'duration' => 30,
            'timeout' => 10
        ];

        $errors = $this->validator->validateConfigurationData($invalidData);
        
        $this->assertNotEmpty($errors);
        $this->assertContains('URL must use HTTP or HTTPS protocol', $errors);
    }

    /**
     * Test validation of excessive load warning
     */
    public function testValidateExcessiveLoadWarning(): void
    {
        $excessiveData = [
            'name' => 'test-config',
            'url' => 'https://example.com',
            'method' => 'GET',
            'concurrentConnections' => 1000,
            'duration' => 200,  // 1000 * 200 = 200,000 > 100,000
            'timeout' => 10
        ];

        $errors = $this->validator->validateConfigurationData($excessiveData);
        
        $this->assertNotEmpty($errors);
        $this->assertContains('Configuration may generate excessive load (consider reducing concurrent connections or duration)', $errors);
    }
}