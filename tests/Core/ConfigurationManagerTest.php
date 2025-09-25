<?php

use OhaGui\Core\ConfigurationManager;
use OhaGui\Core\ConfigurationValidator;
use OhaGui\Models\TestConfiguration;
use OhaGui\Utils\FileManager;

/**
 * Unit tests for ConfigurationManager class
 */
class ConfigurationManagerTest
{
    private ConfigurationManager $configManager;
    private FileManager $fileManager;
    private ConfigurationValidator $validator;
    private string $testConfigDir;

    public function setUp(): void
    {
        // Create temporary directory for test configurations
        $this->testConfigDir = sys_get_temp_dir() . '/oha_gui_test_' . uniqid();
        mkdir($this->testConfigDir, 0755, true);

        // Create mock dependencies
        $this->fileManager = new FileManager($this->testConfigDir);
        $this->validator = new ConfigurationValidator();
        $this->configManager = new ConfigurationManager($this->fileManager, $this->validator);
    }

    public function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->testConfigDir)) {
            $this->removeDirectory($this->testConfigDir);
        }
    }

    /**
     * Test saving a valid configuration
     */
    public function testSaveValidConfiguration(): void
    {
        $config = new TestConfiguration(
            'test-config',
            'https://example.com',
            'GET',
            10,
            30,
            10,
            ['Content-Type' => 'application/json'],
            ''
        );

        $result = $this->configManager->saveConfiguration('test-config', $config);
        
        $this->assertTrue($result);
        $this->assertTrue($this->configManager->configurationExists('test-config'));
    }

    /**
     * Test saving an invalid configuration
     */
    public function testSaveInvalidConfiguration(): void
    {
        $config = new TestConfiguration(
            'invalid-config',
            'invalid-url',  // Invalid URL
            'GET',
            10,
            30,
            10
        );

        $result = $this->configManager->saveConfiguration('invalid-config', $config);
        
        $this->assertFalse($result);
        $this->assertFalse($this->configManager->configurationExists('invalid-config'));
    }

    /**
     * Test loading a valid configuration
     */
    public function testLoadValidConfiguration(): void
    {
        // First save a configuration
        $originalConfig = new TestConfiguration(
            'load-test',
            'https://api.example.com',
            'POST',
            20,
            60,
            15,
            ['Authorization' => 'Bearer token'],
            '{"test": "data"}'
        );

        $this->configManager->saveConfiguration('load-test', $originalConfig);

        // Then load it
        $loadedConfig = $this->configManager->loadConfiguration('load-test');

        $this->assertNotNull($loadedConfig);
        $this->assertEquals('load-test', $loadedConfig->name);
        $this->assertEquals('https://api.example.com', $loadedConfig->url);
        $this->assertEquals('POST', $loadedConfig->method);
        $this->assertEquals(20, $loadedConfig->concurrentConnections);
        $this->assertEquals(60, $loadedConfig->duration);
        $this->assertEquals(15, $loadedConfig->timeout);
        $this->assertEquals(['Authorization' => 'Bearer token'], $loadedConfig->headers);
        $this->assertEquals('{"test": "data"}', $loadedConfig->body);
    }

    /**
     * Test loading non-existent configuration
     */
    public function testLoadNonExistentConfiguration(): void
    {
        $result = $this->configManager->loadConfiguration('non-existent');
        
        $this->assertNull($result);
    }

    /**
     * Test listing configurations
     */
    public function testListConfigurations(): void
    {
        // Save multiple configurations
        $configs = [
            'config1' => new TestConfiguration('config1', 'https://example1.com'),
            'config2' => new TestConfiguration('config2', 'https://example2.com'),
            'config3' => new TestConfiguration('config3', 'https://example3.com')
        ];

        foreach ($configs as $name => $config) {
            $this->configManager->saveConfiguration($name, $config);
        }

        $list = $this->configManager->listConfigurations();

        $this->assertCount(3, $list);
        
        $names = array_column($list, 'name');
        $this->assertContains('config1', $names);
        $this->assertContains('config2', $names);
        $this->assertContains('config3', $names);

        // Check that all configurations are marked as valid
        foreach ($list as $item) {
            $this->assertTrue($item['isValid']);
        }
    }

    /**
     * Test deleting configuration
     */
    public function testDeleteConfiguration(): void
    {
        // Save a configuration
        $config = new TestConfiguration('delete-test', 'https://example.com');
        $this->configManager->saveConfiguration('delete-test', $config);
        
        $this->assertTrue($this->configManager->configurationExists('delete-test'));

        // Delete it
        $result = $this->configManager->deleteConfiguration('delete-test');
        
        $this->assertTrue($result);
        $this->assertFalse($this->configManager->configurationExists('delete-test'));
    }

    /**
     * Test deleting non-existent configuration
     */
    public function testDeleteNonExistentConfiguration(): void
    {
        $result = $this->configManager->deleteConfiguration('non-existent');
        
        $this->assertTrue($result); // Should return true for already deleted
    }

    /**
     * Test duplicating configuration
     */
    public function testDuplicateConfiguration(): void
    {
        // Save original configuration
        $originalConfig = new TestConfiguration(
            'original',
            'https://example.com',
            'GET',
            10,
            30,
            10
        );
        $this->configManager->saveConfiguration('original', $originalConfig);

        // Duplicate it
        $result = $this->configManager->duplicateConfiguration('original', 'duplicate');
        
        $this->assertTrue($result);
        $this->assertTrue($this->configManager->configurationExists('duplicate'));

        // Load both and compare
        $original = $this->configManager->loadConfiguration('original');
        $duplicate = $this->configManager->loadConfiguration('duplicate');

        $this->assertNotNull($original);
        $this->assertNotNull($duplicate);
        $this->assertEquals($original->url, $duplicate->url);
        $this->assertEquals($original->method, $duplicate->method);
        $this->assertEquals($original->concurrentConnections, $duplicate->concurrentConnections);
        
        // Timestamps should be different
        $this->assertNotEquals($original->createdAt, $duplicate->createdAt);
    }

    /**
     * Test importing configuration from JSON
     */
    public function testImportConfiguration(): void
    {
        $jsonContent = json_encode([
            'name' => 'imported-config',
            'url' => 'https://api.example.com',
            'method' => 'POST',
            'concurrentConnections' => 25,
            'duration' => 45,
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => '{"imported": true}',
            'createdAt' => '2024-01-01 12:00:00',
            'updatedAt' => '2024-01-01 12:00:00'
        ]);

        $result = $this->configManager->importConfiguration('imported-config', $jsonContent);
        
        $this->assertTrue($result);
        $this->assertTrue($this->configManager->configurationExists('imported-config'));

        $config = $this->configManager->loadConfiguration('imported-config');
        $this->assertNotNull($config);
        $this->assertEquals('https://api.example.com', $config->url);
        $this->assertEquals('POST', $config->method);
        $this->assertEquals(25, $config->concurrentConnections);
    }

    /**
     * Test importing invalid JSON
     */
    public function testImportInvalidJson(): void
    {
        $invalidJson = '{"name": "test", "url": "invalid-url"'; // Missing closing brace
        
        $result = $this->configManager->importConfiguration('invalid', $invalidJson);
        
        $this->assertFalse($result);
        $this->assertFalse($this->configManager->configurationExists('invalid'));
    }

    /**
     * Test exporting configuration
     */
    public function testExportConfiguration(): void
    {
        // Save a configuration
        $config = new TestConfiguration(
            'export-test',
            'https://example.com',
            'GET',
            10,
            30,
            10,
            ['User-Agent' => 'OHA-GUI'],
            ''
        );
        $this->configManager->saveConfiguration('export-test', $config);

        // Export it
        $json = $this->configManager->exportConfiguration('export-test');
        
        $this->assertNotNull($json);
        
        $data = json_decode($json, true);
        $this->assertNotNull($data);
        $this->assertEquals('export-test', $data['name']);
        $this->assertEquals('https://example.com', $data['url']);
        $this->assertEquals(['User-Agent' => 'OHA-GUI'], $data['headers']);
    }

    /**
     * Test getting validation errors
     */
    public function testGetValidationErrors(): void
    {
        $invalidData = [
            'name' => '',  // Empty name
            'url' => 'invalid-url',  // Invalid URL
            'method' => 'INVALID',  // Invalid method
            'concurrentConnections' => -1,  // Invalid range
            'duration' => 0,  // Invalid range
            'timeout' => 500  // Invalid range
        ];

        $errors = $this->configManager->getValidationErrors($invalidData);
        
        $this->assertNotEmpty($errors);
        $this->assertGreaterThan(5, count($errors)); // Should have multiple errors
    }

    /**
     * Test configuration file validation
     */
    public function testValidateConfigurationFile(): void
    {
        // Save a valid configuration
        $config = new TestConfiguration('validate-test', 'https://example.com');
        $this->configManager->saveConfiguration('validate-test', $config);

        // Validate the file
        $result = $this->configManager->validateConfigurationFile('validate-test');
        
        $this->assertTrue($result['isValid']);
        $this->assertEmpty($result['errors']);
        $this->assertIsArray($result['warnings']);
    }

    /**
     * Test getting configuration schema
     */
    public function testGetConfigurationSchema(): void
    {
        $schema = $this->configManager->getConfigurationSchema();
        
        $this->assertIsArray($schema);
        $this->assertArrayHasKey('type', $schema);
        $this->assertArrayHasKey('required', $schema);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertEquals('object', $schema['type']);
    }

    /**
     * Test backup configuration
     */
    public function testBackupConfiguration(): void
    {
        // Save a configuration
        $config = new TestConfiguration('backup-test', 'https://example.com');
        $this->configManager->saveConfiguration('backup-test', $config);

        // Backup it
        $result = $this->configManager->backupConfiguration('backup-test');
        
        $this->assertTrue($result);
    }

    /**
     * Test getting configuration info
     */
    public function testGetConfigurationInfo(): void
    {
        // Save a configuration
        $config = new TestConfiguration('info-test', 'https://example.com');
        $this->configManager->saveConfiguration('info-test', $config);

        // Get info
        $info = $this->configManager->getConfigurationInfo('info-test');
        
        $this->assertNotNull($info);
        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('path', $info);
        $this->assertArrayHasKey('size', $info);
        $this->assertEquals('info-test', $info['name']);
    }

    /**
     * Test configuration directory access
     */
    public function testGetConfigurationDirectory(): void
    {
        $directory = $this->configManager->getConfigurationDirectory();
        
        $this->assertEquals($this->testConfigDir, $directory);
        $this->assertTrue(is_dir($directory));
    }

    /**
     * Helper method to recursively remove directory
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}