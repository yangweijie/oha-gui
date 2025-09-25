<?php

use OhaGui\Core\OhaCommandBuilder;
use OhaGui\Models\TestConfiguration;

/**
 * Unit tests for OhaCommandBuilder class
 */
class OhaCommandBuilderTest
{
    private OhaCommandBuilder $builder;

    public function setUp(): void
    {
        $this->builder = new OhaCommandBuilder();
        $this->builder->enableTestMode(); // Enable test mode for testing without oha binary
    }

    public function testBuildBasicCommand(): void
    {
        $config = new TestConfiguration(
            'test-config',
            'https://example.com',
            'GET',
            10,
            30,
            15
        );

        $command = $this->builder->buildCommand($config);
        
        // Verify command contains expected components
        assertTrue(strpos($command, '-c 10') !== false, 'Command should contain -c 10');
        assertTrue(strpos($command, '-z 30s') !== false, 'Command should contain -z 30s');
        assertTrue(strpos($command, '-t 15s') !== false, 'Command should contain -t 15s');
        assertTrue(strpos($command, '-m "GET"') !== false, 'Command should contain -m "GET"');
        assertTrue(strpos($command, '--no-tui') !== false, 'Command should contain --no-tui');
        assertTrue(strpos($command, '"https://example.com"') !== false, 'Command should contain URL');
    }

    public function testBuildCommandWithHeaders(): void
    {
        $config = new TestConfiguration(
            'test-config',
            'https://api.example.com',
            'GET',
            5,
            10,
            30,
            [
                'Authorization' => 'Bearer token123',
                'Content-Type' => 'application/json',
                'User-Agent' => 'OhaGui/1.0'
            ]
        );

        $command = $this->builder->buildCommand($config);
        
        assertTrue(strpos($command, '-H "Authorization: Bearer token123"') !== false, 'Command should contain Authorization header');
        assertTrue(strpos($command, '-H "Content-Type: application/json"') !== false, 'Command should contain Content-Type header');
        assertTrue(strpos($command, '-H "User-Agent: OhaGui/1.0"') !== false, 'Command should contain User-Agent header');
    }

    public function testBuildCommandWithBody(): void
    {
        $config = new TestConfiguration(
            'test-config',
            'https://api.example.com/users',
            'POST',
            5,
            10,
            30,
            [],
            '{"name": "John Doe", "email": "john@example.com"}'
        );

        $command = $this->builder->buildCommand($config);
        
        assertTrue(strpos($command, '-m "POST"') !== false, 'Command should contain POST method');
        assertTrue(strpos($command, '-d ') !== false, 'Command should contain -d parameter');
        assertTrue(strpos($command, 'John Doe') !== false, 'Command should contain request body content');
    }

    public function testBuildCommandWithoutBodyForGetMethod(): void
    {
        $config = new TestConfiguration(
            'test-config',
            'https://example.com',
            'GET',
            5,
            10,
            30,
            [],
            '{"ignored": "body"}'  // Body should be ignored for GET
        );

        $command = $this->builder->buildCommand($config);
        
        assertTrue(strpos($command, '-d') === false, 'GET command should not contain -d parameter');
        assertTrue(strpos($command, 'ignored') === false, 'GET command should not contain body content');
    }

    public function testBuildCommandWithPutMethod(): void
    {
        $config = new TestConfiguration(
            'test-config',
            'https://api.example.com/users/1',
            'PUT',
            3,
            15,
            20,
            ['Content-Type' => 'application/json'],
            '{"name": "Jane Doe"}'
        );

        $command = $this->builder->buildCommand($config);
        
        assertTrue(strpos($command, '-m "PUT"') !== false, 'Command should contain PUT method');
        assertTrue(strpos($command, '-d ') !== false, 'Command should contain -d parameter');
        assertTrue(strpos($command, 'Jane Doe') !== false, 'Command should contain request body content');
    }

    public function testBuildCommandWithPatchMethod(): void
    {
        $config = new TestConfiguration(
            'test-config',
            'https://api.example.com/users/1',
            'PATCH',
            2,
            5,
            10,
            [],
            '{"email": "newemail@example.com"}'
        );

        $command = $this->builder->buildCommand($config);
        
        assertTrue(strpos($command, '-m "PATCH"') !== false, 'Command should contain PATCH method');
        assertTrue(strpos($command, '-d ') !== false, 'Command should contain -d parameter');
        assertTrue(strpos($command, 'newemail@example.com') !== false, 'Command should contain request body content');
    }

    public function testBuildCommandWithDeleteMethod(): void
    {
        $config = new TestConfiguration(
            'test-config',
            'https://api.example.com/users/1',
            'DELETE',
            1,
            5,
            10
        );

        $command = $this->builder->buildCommand($config);
        
        assertTrue(strpos($command, '-m "DELETE"') !== false, 'Command should contain DELETE method');
        // DELETE typically doesn't have body, so no -d should be present
        assertTrue(strpos($command, '-d') === false, 'DELETE command should not contain -d parameter');
    }

    public function testBuildCommandWithSpecialCharactersInUrl(): void
    {
        $config = new TestConfiguration(
            'test-config',
            'https://example.com/path?param=value&other=test',
            'GET',
            5,
            10,
            30
        );

        $command = $this->builder->buildCommand($config);
        
        // URL should be properly escaped
        assertTrue(strpos($command, '"https://example.com/path?param=value&other=test"') !== false, 'URL should be properly escaped');
    }

    public function testBuildCommandWithInvalidConfiguration(): void
    {
        $config = new TestConfiguration(
            'test-config',
            'invalid-url',  // Invalid URL
            'GET',
            5,
            10,
            30
        );

        try {
            $this->builder->buildCommand($config);
            assertTrue(false, 'Should have thrown InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            assertTrue(strpos($e->getMessage(), 'Invalid configuration') !== false, 'Exception should mention invalid configuration');
        }
    }

    public function testBuildCommandWithEmptyUrl(): void
    {
        $config = new TestConfiguration(
            'test-config',
            '',  // Empty URL
            'GET',
            5,
            10,
            30
        );

        try {
            $this->builder->buildCommand($config);
            assertTrue(false, 'Should have thrown InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            assertTrue(strpos($e->getMessage(), 'Invalid configuration') !== false, 'Exception should mention invalid configuration');
        }
    }

    public function testBuildTestCommand(): void
    {
        $command = $this->builder->buildTestCommand();
        
        assertTrue(strpos($command, '-c 1') !== false, 'Test command should contain -c 1');
        assertTrue(strpos($command, '-z 1s') !== false, 'Test command should contain -z 1s');
        assertTrue(strpos($command, '--no-tui') !== false, 'Test command should contain --no-tui');
        assertTrue(strpos($command, '"https://httpbin.org/get"') !== false, 'Test command should contain default URL');
    }

    public function testBuildTestCommandWithCustomUrl(): void
    {
        $testUrl = 'https://example.com/test';
        $command = $this->builder->buildTestCommand($testUrl);
        
        assertTrue(strpos($command, '"https://example.com/test"') !== false, 'Test command should contain custom URL');
    }
}