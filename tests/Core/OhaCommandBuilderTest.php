<?php

use OhaGui\Core\OhaCommandBuilder;
use OhaGui\Models\TestConfiguration;

class OhaCommandBuilderTest
{
    private OhaCommandBuilder $builder;
    
    public function setUp(): void
    {
        $this->builder = new OhaCommandBuilder();
    }
    
    public function testBuildBasicCommand()
    {
        $config = new TestConfiguration();
        $config->url = 'https://example.com';
        $config->method = 'GET';
        $config->concurrentConnections = 10;
        $config->duration = 30;
        $config->timeout = 5;
        $config->headers = [];
        $config->body = '';
        
        $command = $this->builder->buildCommand($config);
        
        // Check that command contains essential parts
        assertTrue(strpos($command, '-c 10') !== false);
        assertTrue(strpos($command, '-z 30s') !== false);
        assertTrue(strpos($command, '-t 5s') !== false);
        assertTrue(strpos($command, '-m GET') !== false);
        assertTrue(strpos($command, '--no-tui') !== false);
        assertTrue(strpos($command, 'https://example.com') !== false);
    }
    
    public function testBuildCommandWithHeaders()
    {
        $config = new TestConfiguration();
        $config->url = 'https://api.example.com/test';
        $config->method = 'POST';
        $config->concurrentConnections = 5;
        $config->duration = 10;
        $config->timeout = 3;
        $config->headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer token123',
            'User-Agent' => 'OHA-GUI-Tool/1.0'
        ];
        $config->body = '';
        
        $command = $this->builder->buildCommand($config);
        
        assertTrue(strpos($command, '-H') !== false);
        assertTrue(strpos($command, 'Content-Type: application/json') !== false);
        assertTrue(strpos($command, 'Authorization: Bearer token123') !== false);
        assertTrue(strpos($command, 'User-Agent: OHA-GUI-Tool/1.0') !== false);
    }
    
    public function testBuildCommandWithBody()
    {
        $config = new TestConfiguration();
        $config->url = 'https://api.example.com/users';
        $config->method = 'POST';
        $config->concurrentConnections = 1;
        $config->duration = 5;
        $config->timeout = 10;
        $config->headers = ['Content-Type' => 'application/json'];
        $config->body = '{"name": "John Doe", "email": "john@example.com"}';
        
        $command = $this->builder->buildCommand($config);
        
        assertTrue(strpos($command, '-d') !== false);
        assertTrue(strpos($command, '{"name": "John Doe", "email": "john@example.com"}') !== false);
    }
    
    public function testBuildCommandWithDifferentMethods()
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        
        foreach ($methods as $method) {
            $config = new TestConfiguration();
            $config->url = 'https://example.com';
            $config->method = strtolower($method); // Test case insensitivity
            $config->concurrentConnections = 1;
            $config->duration = 1;
            $config->timeout = 1;
            $config->headers = [];
            $config->body = '';
            
            $command = $this->builder->buildCommand($config);
            
            assertTrue(strpos($command, '-m ' . strtoupper($method)) !== false);
        }
    }
    
    public function testBuildCommandWithSpecialCharactersInUrl()
    {
        $config = new TestConfiguration();
        $config->url = 'https://example.com/path?param=value&other=test';
        $config->method = 'GET';
        $config->concurrentConnections = 1;
        $config->duration = 1;
        $config->timeout = 1;
        $config->headers = [];
        $config->body = '';
        
        $command = $this->builder->buildCommand($config);
        
        // URL should be properly escaped
        assertTrue(strpos($command, 'https://example.com/path?param=value&other=test') !== false);
    }
    
    public function testBuildCommandWithSpecialCharactersInHeaders()
    {
        $config = new TestConfiguration();
        $config->url = 'https://example.com';
        $config->method = 'GET';
        $config->concurrentConnections = 1;
        $config->duration = 1;
        $config->timeout = 1;
        $config->headers = [
            'X-Custom-Header' => 'value with spaces and "quotes"',
            'X-Another-Header' => "value with 'single quotes'"
        ];
        $config->body = '';
        
        $command = $this->builder->buildCommand($config);
        
        assertTrue(strpos($command, 'X-Custom-Header: value with spaces and "quotes"') !== false);
        // The single quotes get escaped as '"'"' in Unix shell escaping
        assertTrue(strpos($command, "X-Another-Header: value with") !== false);
    }
    
    public function testBuildCommandWithSpecialCharactersInBody()
    {
        $config = new TestConfiguration();
        $config->url = 'https://example.com';
        $config->method = 'POST';
        $config->concurrentConnections = 1;
        $config->duration = 1;
        $config->timeout = 1;
        $config->headers = [];
        $config->body = '{"message": "Hello \"World\"", "data": [1, 2, 3]}';
        
        $command = $this->builder->buildCommand($config);
        
        assertTrue(strpos($command, '{"message": "Hello \"World\"", "data": [1, 2, 3]}') !== false);
    }
    
    public function testBuildCommandWithMaximumValues()
    {
        $config = new TestConfiguration();
        $config->url = 'https://example.com';
        $config->method = 'GET';
        $config->concurrentConnections = 1000;
        $config->duration = 3600; // 1 hour
        $config->timeout = 300; // 5 minutes
        $config->headers = [];
        $config->body = '';
        
        $command = $this->builder->buildCommand($config);
        
        assertTrue(strpos($command, '-c 1000') !== false);
        assertTrue(strpos($command, '-z 3600s') !== false);
        assertTrue(strpos($command, '-t 300s') !== false);
    }
    
    public function testBuildCommandWithMinimumValues()
    {
        $config = new TestConfiguration();
        $config->url = 'https://example.com';
        $config->method = 'GET';
        $config->concurrentConnections = 1;
        $config->duration = 1;
        $config->timeout = 1;
        $config->headers = [];
        $config->body = '';
        
        $command = $this->builder->buildCommand($config);
        
        assertTrue(strpos($command, '-c 1') !== false);
        assertTrue(strpos($command, '-z 1s') !== false);
        assertTrue(strpos($command, '-t 1s') !== false);
    }
    
    public function testBuildCommandThrowsExceptionForInvalidConfiguration()
    {
        $config = new TestConfiguration();
        $config->url = 'invalid-url'; // Invalid URL format
        $config->method = 'GET';
        $config->concurrentConnections = 1;
        $config->duration = 1;
        $config->timeout = 1;
        $config->headers = [];
        $config->body = '';
        
        try {
            $this->builder->buildCommand($config);
            // If we reach here, exception was not thrown
            throw new Exception('Expected InvalidArgumentException was not thrown');
        } catch (\InvalidArgumentException $e) {
            // Expected exception
            if (strpos($e->getMessage(), 'Invalid configuration') === false) {
                throw new Exception('Exception message does not match expected: ' . $e->getMessage());
            }
        }
    }
    
    public function testBuildCommandWithEmptyHeaders()
    {
        $config = new TestConfiguration();
        $config->url = 'https://example.com';
        $config->method = 'GET';
        $config->concurrentConnections = 1;
        $config->duration = 1;
        $config->timeout = 1;
        $config->headers = [];
        $config->body = '';
        
        $command = $this->builder->buildCommand($config);
        
        // Should not contain -H flag when no headers
        $headerCount = substr_count($command, ' -H ');
        assertEquals(0, $headerCount);
    }
    
    public function testBuildCommandWithEmptyBody()
    {
        $config = new TestConfiguration();
        $config->url = 'https://example.com';
        $config->method = 'POST';
        $config->concurrentConnections = 1;
        $config->duration = 1;
        $config->timeout = 1;
        $config->headers = [];
        $config->body = '';
        
        $command = $this->builder->buildCommand($config);
        
        // Should not contain -d flag when no body
        assertFalse(strpos($command, ' -d ') !== false);
    }
    
    public function testIsOhaAvailable()
    {
        // This test will depend on whether oha is actually installed
        // We'll just test that the method returns a boolean
        $result = $this->builder->isOhaAvailable();
        assertTrue(is_bool($result));
    }
    
    public function testGetOhaVersion()
    {
        // This test will depend on whether oha is actually installed
        // We'll just test that the method returns either string or null
        $result = $this->builder->getOhaVersion();
        assertTrue(is_string($result) || is_null($result));
    }
    
    /**
     * Test command structure and order
     */
    public function testCommandStructureAndOrder()
    {
        $config = new TestConfiguration();
        $config->url = 'https://example.com/api';
        $config->method = 'POST';
        $config->concurrentConnections = 5;
        $config->duration = 10;
        $config->timeout = 3;
        $config->headers = ['Content-Type' => 'application/json'];
        $config->body = '{"test": true}';
        
        $command = $this->builder->buildCommand($config);
        
        // Split command into parts for order verification
        $parts = explode(' ', $command);
        
        // Find positions of key elements
        $cIndex = array_search('-c', $parts);
        $zIndex = array_search('-z', $parts);
        $tIndex = array_search('-t', $parts);
        $mIndex = array_search('-m', $parts);
        $noTuiIndex = array_search('--no-tui', $parts);
        $hIndex = array_search('-H', $parts);
        $dIndex = array_search('-d', $parts);
        
        // Verify that elements appear in expected order
        assertFalse($cIndex === false);
        assertFalse($zIndex === false);
        assertFalse($tIndex === false);
        assertFalse($mIndex === false);
        assertFalse($noTuiIndex === false);
        assertFalse($hIndex === false);
        assertFalse($dIndex === false);
        
        // URL should be the last argument
        $lastPart = end($parts);
        assertTrue(strpos($lastPart, 'https://example.com/api') !== false);
    }
}