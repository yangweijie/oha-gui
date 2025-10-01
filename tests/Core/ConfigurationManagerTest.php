<?php

use OhaGui\Core\ConfigurationManager;
use OhaGui\Models\TestConfiguration;
use OhaGui\Utils\FileManager;

class ConfigurationManagerTest
{
    private ConfigurationManager $configManager;
    private FileManager $fileManager;
    private string $tempDir;

    public function setUp(): void
    {
        // Create temporary directory for test configurations
        $this->tempDir = sys_get_temp_dir() . '/oha_gui_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        
        // Create file manager with test directory
        $this->fileManager = new FileManager($this->tempDir);
        
        // Create configuration manager with test file manager
        $this->configManager = new ConfigurationManager($this->fileManager);
    }

    public function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createTestConfiguration(string $name = 'test-config'): TestConfiguration
    {
        return new TestConfiguration(
            name: $name,
            url: 'https://example.com/api',
            method: 'GET',
            concurrentConnections: 10,
            duration: 30,
            timeout: 60,
            headers: ['Authorization' => 'Bearer token123'],
            body: ''
        );
    }

    public function testSaveConfiguration(): void
    {
        $config = $this->createTestConfiguration('test-save');
        
        $result = $this->configManager->saveConfiguration('test-save', $config);
        
        assertTrue($result);
        assertTrue($this->configManager->configurationExists('test-save'));
    }

    public function testSaveConfigurationWithEmptyName(): void
    {
        $config = $this->createTestConfiguration();
        
        try {
            $this->configManager->saveConfiguration('', $config);
            // If we reach here, exception was not thrown
            throw new Exception('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            // Expected exception
            if (strpos($e->getMessage(), 'Configuration name cannot be empty') === false) {
                throw new Exception('Exception message does not match expected: ' . $e->getMessage());
            }
        }
    }

    public function testSaveConfigurationWithInvalidConfig(): void
    {
        $config = new TestConfiguration(
            name: 'invalid-config',
            url: 'invalid-url', // Invalid URL
            method: 'GET'
        );
        
        try {
            $this->configManager->saveConfiguration('invalid-config', $config);
            // If we reach here, exception was not thrown
            throw new Exception('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            // Expected exception
            if (strpos($e->getMessage(), 'Configuration validation failed') === false) {
                throw new Exception('Exception message does not match expected: ' . $e->getMessage());
            }
        }
    }

    public function testLoadConfiguration(): void
    {
        $originalConfig = $this->createTestConfiguration('test-load');
        $this->configManager->saveConfiguration('test-load', $originalConfig);
        
        $loadedConfig = $this->configManager->loadConfiguration('test-load');
        
        assertNotNull($loadedConfig);
        assertEquals('test-load', $loadedConfig->name);
        assertEquals('https://example.com/api', $loadedConfig->url);
        assertEquals('GET', $loadedConfig->method);
        assertEquals(10, $loadedConfig->concurrentConnections);
        assertEquals(30, $loadedConfig->duration);
        assertEquals(60, $loadedConfig->timeout);
        assertEquals(['Authorization' => 'Bearer token123'], $loadedConfig->headers);
    }

    public function testLoadNonExistentConfiguration(): void
    {
        $result = $this->configManager->loadConfiguration('non-existent');
        
        assertNull($result);
    }

    public function testLoadConfigurationWithEmptyName(): void
    {
        try {
            $this->configManager->loadConfiguration('');
            // If we reach here, exception was not thrown
            throw new Exception('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            // Expected exception
            if (strpos($e->getMessage(), 'Configuration name cannot be empty') === false) {
                throw new Exception('Exception message does not match expected: ' . $e->getMessage());
            }
        }
    }

    public function testListConfigurations(): void
    {
        // Initially empty
        $configs = $this->configManager->listConfigurations();
        assertEmpty($configs);
        
        // Add some configurations
        $config1 = $this->createTestConfiguration('config1');
        $config2 = $this->createTestConfiguration('config2');
        $config3 = $this->createTestConfiguration('config3');
        
        $this->configManager->saveConfiguration('config1', $config1);
        $this->configManager->saveConfiguration('config2', $config2);
        $this->configManager->saveConfiguration('config3', $config3);
        
        $configs = $this->configManager->listConfigurations();
        
        assertCount(3, $configs);
        assertContains('config1', $configs);
        assertContains('config2', $configs);
        assertContains('config3', $configs);
    }

    public function testDeleteConfiguration(): void
    {
        $config = $this->createTestConfiguration('test-delete');
        $this->configManager->saveConfiguration('test-delete', $config);
        
        // Verify it exists
        assertTrue($this->configManager->configurationExists('test-delete'));
        
        // Delete it
        $result = $this->configManager->deleteConfiguration('test-delete');
        
        assertTrue($result);
        assertFalse($this->configManager->configurationExists('test-delete'));
    }

    public function testDeleteNonExistentConfiguration(): void
    {
        // Should return true even if file doesn't exist
        $result = $this->configManager->deleteConfiguration('non-existent');
        
        assertTrue($result);
    }

    public function testDeleteConfigurationWithEmptyName(): void
    {
        try {
            $this->configManager->deleteConfiguration('');
            // If we reach here, exception was not thrown
            throw new Exception('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            // Expected exception
            if (strpos($e->getMessage(), 'Configuration name cannot be empty') === false) {
                throw new Exception('Exception message does not match expected: ' . $e->getMessage());
            }
        }
    }

    public function testConfigurationExists(): void
    {
        assertFalse($this->configManager->configurationExists('test-exists'));
        
        $config = $this->createTestConfiguration('test-exists');
        $this->configManager->saveConfiguration('test-exists', $config);
        
        assertTrue($this->configManager->configurationExists('test-exists'));
    }

    public function testGetConfigurationPath(): void
    {
        $path = $this->configManager->getConfigurationPath('test-path');
        
        assertTrue(strpos($path, 'test-path.json') !== false);
        assertTrue(strpos($path, $this->tempDir) !== false);
    }

    public function testBackupConfiguration(): void
    {
        $config = $this->createTestConfiguration('test-backup');
        $this->configManager->saveConfiguration('test-backup', $config);
        
        $backupPath = $this->configManager->backupConfiguration('test-backup');
        
        assertNotNull($backupPath);
        assertTrue(file_exists($backupPath));
        assertTrue(strpos($backupPath, 'test-backup_backup_') !== false);
    }

    public function testBackupNonExistentConfiguration(): void
    {
        $result = $this->configManager->backupConfiguration('non-existent');
        
        assertNull($result);
    }

    public function testGetConfigurationMetadata(): void
    {
        $config = $this->createTestConfiguration('test-metadata');
        $this->configManager->saveConfiguration('test-metadata', $config);
        
        $metadata = $this->configManager->getConfigurationMetadata('test-metadata');
        
        assertNotNull($metadata);
        assertEquals('test-metadata', $metadata['name']);
        assertArrayHasKey('path', $metadata);
        assertArrayHasKey('modified_time', $metadata);
        assertArrayHasKey('size', $metadata);
        assertTrue($metadata['exists']);
    }

    public function testGetConfigurationMetadataForNonExistent(): void
    {
        $metadata = $this->configManager->getConfigurationMetadata('non-existent');
        
        assertNull($metadata);
    }

    public function testValidateConfigurationFile(): void
    {
        $config = $this->createTestConfiguration('test-validate');
        $this->configManager->saveConfiguration('test-validate', $config);
        
        assertTrue($this->configManager->validateConfigurationFile('test-validate'));
        assertFalse($this->configManager->validateConfigurationFile('non-existent'));
    }

    public function testGetConfigurationSummaries(): void
    {
        $config1 = $this->createTestConfiguration('summary1');
        $config2 = $this->createTestConfiguration('summary2');
        $config2->url = 'https://api.github.com/repos';
        $config2->method = 'POST';
        
        $this->configManager->saveConfiguration('summary1', $config1);
        $this->configManager->saveConfiguration('summary2', $config2);
        
        $summaries = $this->configManager->getConfigurationSummaries();
        
        assertCount(2, $summaries);
        
        $summary1 = null;
        $summary2 = null;
        
        foreach ($summaries as $summary) {
            if ($summary['name'] === 'summary1') {
                $summary1 = $summary;
            } elseif ($summary['name'] === 'summary2') {
                $summary2 = $summary;
            }
        }
        
        assertNotNull($summary1);
        assertEquals('https://example.com/api', $summary1['url']);
        assertEquals('GET', $summary1['method']);
        assertTrue(strpos($summary1['summary'], 'GET https://example.com/api') !== false);
        
        assertNotNull($summary2);
        assertEquals('https://api.github.com/repos', $summary2['url']);
        assertEquals('POST', $summary2['method']);
        assertTrue(strpos($summary2['summary'], 'POST https://api.github.com/repos') !== false);
    }

    public function testGenerateConfigurationSummary(): void
    {
        $config = $this->createTestConfiguration('test-summary');
        $config->url = 'https://very-long-url-that-should-be-truncated-because-it-exceeds-fifty-characters.com/api/endpoint';
        
        $summary = $this->configManager->generateConfigurationSummary($config);
        
        assertTrue(strpos($summary, 'GET') !== false);
        assertTrue(strpos($summary, '(10 conn, 30s)') !== false);
        assertTrue(strpos($summary, '...') !== false); // URL should be truncated
        assertTrue(strlen($summary) <= 100); // Summary should be reasonably short
    }

    public function testImportConfiguration(): void
    {
        $data = [
            'name' => 'imported-config',
            'url' => 'https://import.example.com',
            'method' => 'POST',
            'concurrentConnections' => 5,
            'duration' => 15,
            'timeout' => 45,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => '{"test": true}',
            'createdAt' => '2023-01-01 12:00:00',
            'updatedAt' => '2023-01-01 12:00:00'
        ];
        
        $result = $this->configManager->importConfiguration('imported-config', $data);
        
        assertTrue($result);
        assertTrue($this->configManager->configurationExists('imported-config'));
        
        $loadedConfig = $this->configManager->loadConfiguration('imported-config');
        assertEquals('https://import.example.com', $loadedConfig->url);
        assertEquals('POST', $loadedConfig->method);
    }

    public function testExportConfiguration(): void
    {
        $config = $this->createTestConfiguration('test-export');
        $this->configManager->saveConfiguration('test-export', $config);
        
        $exportedData = $this->configManager->exportConfiguration('test-export');
        
        assertNotNull($exportedData);
        assertIsArray($exportedData);
        assertEquals('test-export', $exportedData['name']);
        assertEquals('https://example.com/api', $exportedData['url']);
        assertEquals('GET', $exportedData['method']);
    }

    public function testExportNonExistentConfiguration(): void
    {
        $result = $this->configManager->exportConfiguration('non-existent');
        
        assertNull($result);
    }

    public function testDuplicateConfiguration(): void
    {
        $originalConfig = $this->createTestConfiguration('original');
        $this->configManager->saveConfiguration('original', $originalConfig);
        
        $result = $this->configManager->duplicateConfiguration('original', 'duplicate');
        
        assertTrue($result);
        assertTrue($this->configManager->configurationExists('duplicate'));
        
        $duplicatedConfig = $this->configManager->loadConfiguration('duplicate');
        assertEquals('duplicate', $duplicatedConfig->name);
        assertEquals($originalConfig->url, $duplicatedConfig->url);
        assertEquals($originalConfig->method, $duplicatedConfig->method);
        
        // Timestamps should be different
        assertNotEquals($originalConfig->createdAt, $duplicatedConfig->createdAt);
    }

    public function testDuplicateNonExistentConfiguration(): void
    {
        try {
            $this->configManager->duplicateConfiguration('non-existent', 'target');
            // If we reach here, exception was not thrown
            throw new Exception('Expected InvalidArgumentException was not thrown');
        } catch (InvalidArgumentException $e) {
            // Expected exception
            if (strpos($e->getMessage(), "Source configuration 'non-existent' does not exist") === false) {
                throw new Exception('Exception message does not match expected: ' . $e->getMessage());
            }
        }
    }

    public function testGetStorageInfo(): void
    {
        $config1 = $this->createTestConfiguration('storage1');
        $config2 = $this->createTestConfiguration('storage2');
        
        $this->configManager->saveConfiguration('storage1', $config1);
        $this->configManager->saveConfiguration('storage2', $config2);
        
        $storageInfo = $this->configManager->getStorageInfo();
        
        assertArrayHasKey('configuration_count', $storageInfo);
        assertArrayHasKey('total_size', $storageInfo);
        assertArrayHasKey('config_directory', $storageInfo);
        assertArrayHasKey('disk_free', $storageInfo);
        assertArrayHasKey('disk_total', $storageInfo);
        assertArrayHasKey('disk_used', $storageInfo);
        
        assertEquals(2, $storageInfo['configuration_count']);
        assertGreaterThan(0, $storageInfo['total_size']);
        assertEquals($this->tempDir, $storageInfo['config_directory']);
    }

    public function testSaveAndLoadComplexConfiguration(): void
    {
        $config = new TestConfiguration(
            name: 'complex-config',
            url: 'https://api.example.com/v1/users',
            method: 'POST',
            concurrentConnections: 50,
            duration: 120,
            timeout: 90,
            headers: [
                'Authorization' => 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9',
                'Content-Type' => 'application/json',
                'User-Agent' => 'OhaGui/1.0',
                'Accept' => 'application/json'
            ],
            body: '{"username": "testuser", "email": "test@example.com", "active": true}'
        );
        
        $this->configManager->saveConfiguration('complex-config', $config);
        $loadedConfig = $this->configManager->loadConfiguration('complex-config');
        
        assertNotNull($loadedConfig);
        assertEquals($config->name, $loadedConfig->name);
        assertEquals($config->url, $loadedConfig->url);
        assertEquals($config->method, $loadedConfig->method);
        assertEquals($config->concurrentConnections, $loadedConfig->concurrentConnections);
        assertEquals($config->duration, $loadedConfig->duration);
        assertEquals($config->timeout, $loadedConfig->timeout);
        assertEquals($config->headers, $loadedConfig->headers);
        assertEquals($config->body, $loadedConfig->body);
    }
}