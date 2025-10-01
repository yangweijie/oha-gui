<?php

use OhaGui\Core\ConfigurationValidator;

class ConfigurationValidatorTest
{
    private ConfigurationValidator $validator;

    public function setUp(): void
    {
        $this->validator = new ConfigurationValidator();
    }

    private function getValidConfigurationData(): array
    {
        return [
            'name' => 'test-config',
            'url' => 'https://example.com/api',
            'method' => 'GET',
            'concurrentConnections' => 10,
            'duration' => 30,
            'timeout' => 60,
            'headers' => ['Authorization' => 'Bearer token123'],
            'body' => '',
            'createdAt' => '2023-01-01 12:00:00',
            'updatedAt' => '2023-01-01 12:00:00'
        ];
    }

    public function testValidateValidConfiguration(): void
    {
        $data = $this->getValidConfigurationData();
        $errors = $this->validator->validateConfiguration($data);
        
        assertEmpty($errors);
    }

    public function testValidateConfigurationMissingRequiredFields(): void
    {
        $data = [];
        $errors = $this->validator->validateConfiguration($data);
        
        assertNotEmpty($errors);
        assertContains("Required field 'name' is missing or empty", $errors);
        assertContains("Required field 'url' is missing or empty", $errors);
        assertContains("Required field 'method' is missing or empty", $errors);
    }

    public function testValidateConfigurationEmptyRequiredFields(): void
    {
        $data = [
            'name' => '',
            'url' => '',
            'method' => ''
        ];
        $errors = $this->validator->validateConfiguration($data);
        
        assertNotEmpty($errors);
        assertContains("Required field 'name' is missing or empty", $errors);
        assertContains("Required field 'url' is missing or empty", $errors);
        assertContains("Required field 'method' is missing or empty", $errors);
    }

    public function testValidateNameTooShort(): void
    {
        $data = $this->getValidConfigurationData();
        $data['name'] = '';
        
        $errors = $this->validator->validateConfiguration($data);
        
        assertContains("Required field 'name' is missing or empty", $errors);
    }

    public function testValidateNameTooLong(): void
    {
        $data = $this->getValidConfigurationData();
        $data['name'] = str_repeat('a', 101);
        
        $errors = $this->validator->validateConfiguration($data);
        
        assertContains('Configuration name must be no more than 100 characters long', $errors);
    }

    public function testValidateNameInvalidCharacters(): void
    {
        $data = $this->getValidConfigurationData();
        $data['name'] = 'invalid/name*with?special<chars>';
        
        $errors = $this->validator->validateConfiguration($data);
        
        assertContains('Configuration name can only contain letters, numbers, spaces, hyphens, and underscores', $errors);
    }

    public function testValidateNameValidCharacters(): void
    {
        $data = $this->getValidConfigurationData();
        $data['name'] = 'Valid_Name-123 with spaces';
        
        $errors = $this->validator->validateConfiguration($data);
        
        // Should not have name-related errors
        $nameErrors = array_filter($errors, fn($error) => strpos($error, 'name') !== false);
        assertEmpty($nameErrors);
    }

    public function testValidateInvalidUrl(): void
    {
        $data = $this->getValidConfigurationData();
        $data['url'] = 'not-a-valid-url';
        
        $errors = $this->validator->validateConfiguration($data);
        
        assertContains('URL format is invalid', $errors);
    }

    public function testValidateUrlWithoutScheme(): void
    {
        $data = $this->getValidConfigurationData();
        $data['url'] = 'example.com/api';
        
        $errors = $this->validator->validateConfiguration($data);
        
        assertContains('URL format is invalid', $errors);
    }

    public function testValidateUrlWithInvalidScheme(): void
    {
        $data = $this->getValidConfigurationData();
        $data['url'] = 'ftp://example.com/api';
        
        $errors = $this->validator->validateConfiguration($data);
        
        assertContains('URL scheme must be http or https', $errors);
    }

    public function testValidateValidUrls(): void
    {
        $validUrls = [
            'http://example.com',
            'https://example.com',
            'https://api.example.com/v1/users',
            'http://localhost:8080/api',
            'https://subdomain.example.com:443/path?query=value'
        ];

        foreach ($validUrls as $url) {
            $data = $this->getValidConfigurationData();
            $data['url'] = $url;
            
            $errors = $this->validator->validateConfiguration($data);
            $urlErrors = array_filter($errors, fn($error) => strpos($error, 'URL') !== false);
            
            assertTrue(empty($urlErrors), "URL '{$url}' should be valid");
        }
    }

    public function testValidateInvalidMethod(): void
    {
        $data = $this->getValidConfigurationData();
        $data['method'] = 'INVALID';
        
        $errors = $this->validator->validateConfiguration($data);
        
        assertContains('HTTP method must be one of: GET, POST, PUT, DELETE, PATCH', $errors);
    }

    public function testValidateValidMethods(): void
    {
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        foreach ($validMethods as $method) {
            $data = $this->getValidConfigurationData();
            $data['method'] = $method;
            
            $errors = $this->validator->validateConfiguration($data);
            $methodErrors = array_filter($errors, fn($error) => strpos($error, 'method') !== false);
            
            assertTrue(empty($methodErrors), "Method '{$method}' should be valid");
        }
    }

    public function testValidateConcurrentConnectionsOutOfRange(): void
    {
        $data = $this->getValidConfigurationData();
        
        // Test minimum
        $data['concurrentConnections'] = 0;
        $errors = $this->validator->validateConfiguration($data);
        assertContains('Concurrent connections must be at least 1', $errors);
        
        // Test maximum
        $data['concurrentConnections'] = 1001;
        $errors = $this->validator->validateConfiguration($data);
        assertContains('Concurrent connections must be no more than 1000', $errors);
    }

    public function testValidateConcurrentConnectionsInvalidType(): void
    {
        $data = $this->getValidConfigurationData();
        $data['concurrentConnections'] = 'not-a-number';
        
        $errors = $this->validator->validateConfiguration($data);
        
        assertContains('Concurrent connections must be a number', $errors);
    }

    public function testValidateDurationOutOfRange(): void
    {
        $data = $this->getValidConfigurationData();
        
        // Test minimum
        $data['duration'] = 0;
        $errors = $this->validator->validateConfiguration($data);
        assertContains('Duration must be at least 1 second(s)', $errors);
        
        // Test maximum
        $data['duration'] = 3601;
        $errors = $this->validator->validateConfiguration($data);
        assertContains('Duration must be no more than 3600 seconds', $errors);
    }

    public function testValidateTimeoutOutOfRange(): void
    {
        $data = $this->getValidConfigurationData();
        
        // Test minimum
        $data['timeout'] = 0;
        $errors = $this->validator->validateConfiguration($data);
        assertContains('Timeout must be at least 1 second(s)', $errors);
        
        // Test maximum
        $data['timeout'] = 301;
        $errors = $this->validator->validateConfiguration($data);
        assertContains('Timeout must be no more than 300 seconds', $errors);
    }

    public function testValidateHeadersInvalidType(): void
    {
        $data = $this->getValidConfigurationData();
        $data['headers'] = 'not-an-array';
        
        $errors = $this->validator->validateConfiguration($data);
        
        assertContains('Headers must be an array/object', $errors);
    }

    public function testValidateHeadersInvalidKeys(): void
    {
        $data = $this->getValidConfigurationData();
        $data['headers'] = ['' => 'value', 'valid-key' => 'value'];
        
        $errors = $this->validator->validateConfiguration($data);
        
        assertContains('Header keys must be non-empty strings', $errors);
    }

    public function testValidateHeadersInvalidValues(): void
    {
        $data = $this->getValidConfigurationData();
        $data['headers'] = ['key' => 123];
        
        $errors = $this->validator->validateConfiguration($data);
        
        assertContains('Header values must be strings', $errors);
    }

    public function testValidateBodyInvalidType(): void
    {
        $data = $this->getValidConfigurationData();
        $data['body'] = 123;
        
        $errors = $this->validator->validateConfiguration($data);
        
        assertContains('Request body must be a string', $errors);
    }

    public function testValidateBodyInvalidJsonForPostMethod(): void
    {
        $data = $this->getValidConfigurationData();
        $data['method'] = 'POST';
        $data['body'] = 'invalid json content';
        
        $errors = $this->validator->validateConfiguration($data);
        
        assertContains('Request body must be valid JSON or form data for POST requests', $errors);
    }

    public function testValidateBodyValidJsonForPostMethod(): void
    {
        $data = $this->getValidConfigurationData();
        $data['method'] = 'POST';
        $data['body'] = '{"key": "value"}';
        
        $errors = $this->validator->validateConfiguration($data);
        $bodyErrors = array_filter($errors, fn($error) => strpos($error, 'body') !== false);
        
        assertEmpty($bodyErrors);
    }

    public function testValidateBodyValidFormDataForPostMethod(): void
    {
        $data = $this->getValidConfigurationData();
        $data['method'] = 'POST';
        $data['body'] = 'key1=value1&key2=value2';
        
        $errors = $this->validator->validateConfiguration($data);
        $bodyErrors = array_filter($errors, fn($error) => strpos($error, 'body') !== false);
        
        assertEmpty($bodyErrors);
    }

    public function testValidateTimestampsInvalidFormat(): void
    {
        $data = $this->getValidConfigurationData();
        $data['createdAt'] = 'invalid-date';
        $data['updatedAt'] = '2023/01/01 12:00:00'; // Wrong format
        
        $errors = $this->validator->validateConfiguration($data);
        
        assertContains("createdAt must be in format 'Y-m-d H:i:s'", $errors);
        assertContains("updatedAt must be in format 'Y-m-d H:i:s'", $errors);
    }

    public function testValidateTimestampsValidFormat(): void
    {
        $data = $this->getValidConfigurationData();
        $data['createdAt'] = '2023-01-01 12:00:00';
        $data['updatedAt'] = '2023-12-31 23:59:59';
        
        $errors = $this->validator->validateConfiguration($data);
        $timestampErrors = array_filter($errors, fn($error) => 
            strpos($error, 'createdAt') !== false || strpos($error, 'updatedAt') !== false
        );
        
        assertEmpty($timestampErrors);
    }

    public function testValidateConfigurationFileValidJson(): void
    {
        $data = $this->getValidConfigurationData();
        $jsonContent = json_encode($data);
        
        $errors = $this->validator->validateConfigurationFile($jsonContent);
        
        assertEmpty($errors);
    }

    public function testValidateConfigurationFileInvalidJson(): void
    {
        $invalidJson = '{"name": "test", "url": "https://example.com", "method": "GET"'; // Missing closing brace
        
        $errors = $this->validator->validateConfigurationFile($invalidJson);
        
        assertNotEmpty($errors);
        assertTrue(strpos($errors[0], 'Invalid JSON format') !== false);
    }

    public function testValidateConfigurationFileNotObject(): void
    {
        $jsonContent = '["not", "an", "object"]';
        
        $errors = $this->validator->validateConfigurationFile($jsonContent);
        
        assertContains('Configuration file must contain a JSON object', $errors);
    }

    public function testGetSchema(): void
    {
        $schema = $this->validator->getSchema();
        
        assertIsArray($schema);
        assertArrayHasKey('type', $schema);
        assertArrayHasKey('required', $schema);
        assertArrayHasKey('properties', $schema);
        assertEquals('object', $schema['type']);
    }

    public function testGetDefaultValues(): void
    {
        $defaults = $this->validator->getDefaultValues();
        
        assertIsArray($defaults);
        assertEquals(1, $defaults['concurrentConnections']);
        assertEquals(2, $defaults['duration']);
        assertEquals(30, $defaults['timeout']);
        assertEquals([], $defaults['headers']);
        assertEquals('', $defaults['body']);
    }

    public function testSanitizeConfiguration(): void
    {
        $data = [
            'name' => '  test-config  ',
            'url' => '  https://example.com/api  ',
            'method' => 'get',
            'concurrentConnections' => '10',
            'duration' => '30.5',
            'timeout' => '60',
            'headers' => 'not-an-array',
            'body' => 123
        ];
        
        $sanitized = $this->validator->sanitizeConfiguration($data);
        
        assertEquals('test-config', $sanitized['name']);
        assertEquals('https://example.com/api', $sanitized['url']);
        assertEquals('GET', $sanitized['method']);
        assertEquals(10, $sanitized['concurrentConnections']);
        assertEquals(30, $sanitized['duration']);
        assertEquals(60, $sanitized['timeout']);
        assertEquals([], $sanitized['headers']);
        assertEquals('123', $sanitized['body']);
    }

    public function testSanitizeConfigurationWithDefaults(): void
    {
        $data = [
            'name' => 'minimal-config',
            'url' => 'https://example.com',
            'method' => 'GET'
        ];
        
        $sanitized = $this->validator->sanitizeConfiguration($data);
        
        assertEquals('minimal-config', $sanitized['name']);
        assertEquals('https://example.com', $sanitized['url']);
        assertEquals('GET', $sanitized['method']);
        assertEquals(1, $sanitized['concurrentConnections']);
        assertEquals(2, $sanitized['duration']);
        assertEquals(30, $sanitized['timeout']);
        assertEquals([], $sanitized['headers']);
        assertEquals('', $sanitized['body']);
    }

    public function testIsValid(): void
    {
        $validData = $this->getValidConfigurationData();
        assertTrue($this->validator->isValid($validData));
        
        $invalidData = ['name' => '', 'url' => 'invalid', 'method' => 'INVALID'];
        assertFalse($this->validator->isValid($invalidData));
    }

    public function testValidateOrThrowWithValidData(): void
    {
        $validData = $this->getValidConfigurationData();
        
        // Should not throw exception
        $this->validator->validateOrThrow($validData);
        assertTrue(true); // If we reach here, no exception was thrown
    }

    public function testValidateOrThrowWithInvalidData(): void
    {
        $invalidData = ['name' => '', 'url' => 'invalid', 'method' => 'INVALID'];
        
        try {
            $this->validator->validateOrThrow($invalidData);
            // If we reach here, exception was not thrown
            throw new Exception('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            // Expected exception
            if (strpos($e->getMessage(), 'Configuration validation failed') === false) {
                throw new Exception('Exception message does not match expected: ' . $e->getMessage());
            }
        }
    }

    public function testOptionalFieldsAreOptional(): void
    {
        $minimalData = [
            'name' => 'minimal-config',
            'url' => 'https://example.com',
            'method' => 'GET'
        ];
        
        $errors = $this->validator->validateConfiguration($minimalData);
        
        assertEmpty($errors);
    }

    public function testNumericStringConversion(): void
    {
        $data = $this->getValidConfigurationData();
        $data['concurrentConnections'] = '10';
        $data['duration'] = '30';
        $data['timeout'] = '60';
        
        $errors = $this->validator->validateConfiguration($data);
        
        assertEmpty($errors);
    }
}