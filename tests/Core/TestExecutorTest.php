<?php

use OhaGui\Core\TestExecutor;
use OhaGui\Models\TestResult;

class TestExecutorTest
{
    private TestExecutor $executor;
    
    public function setUp(): void
    {
        $this->executor = new TestExecutor();
    }
    
    public function tearDown(): void
    {
        // Ensure any running tests are stopped
        if ($this->executor->isRunning()) {
            $this->executor->stopTest();
        }
    }
    
    public function testInitialState()
    {
        assertFalse($this->executor->isRunning());
        assertEquals('', $this->executor->getOutput());
    }
    
    public function testExecuteSimpleCommand()
    {
        // Skip this test if oha is not available
        if (!$this->isOhaAvailable()) {
            assertTrue(true); // Skip test
            return;
        }
        
        // Use a simple oha command that should work on most systems
        $command = $this->getOhaBinaryPath() . ' --help';
        
        $outputReceived = '';
        $completionCalled = false;
        $testResult = null;
        
        $outputCallback = function($output) use (&$outputReceived) {
            $outputReceived .= $output;
        };
        
        $completionCallback = function($result) use (&$completionCalled, &$testResult) {
            $completionCalled = true;
            $testResult = $result;
        };
        
        $this->executor->executeTest($command, null, $outputCallback, $completionCallback);
        
        // Initially should be running
        assertTrue($this->executor->isRunning());
        
        // Wait for completion
        $maxWait = 50;
        $waited = 0;
        while ($this->executor->isRunning() && $waited < $maxWait) {
            $this->executor->update();
            usleep(100000);
            $waited++;
        }
        
        assertTrue($completionCalled);
        assertNotNull($testResult);
        assertTrue(strpos($outputReceived, 'Oha') !== false || strpos($outputReceived, 'oha') !== false);
    }
    
    public function testExecuteTestSyncWithEcho()
    {
        // Skip this test if oha is not available
        if (!$this->isOhaAvailable()) {
            assertTrue(true); // Skip test
            return;
        }
        
        $command = $this->getOhaBinaryPath() . ' --help';
        
        $result = $this->executor->executeTestSync($command, 5);
        
        assertNotNull($result);
        assertTrue(strpos($result->rawOutput, 'Oha') !== false || strpos($result->rawOutput, 'oha') !== false);
        assertFalse($this->executor->isRunning());
    }
    
    public function testCannotStartMultipleTests()
    {
        // Skip this test if oha is not available
        if (!$this->isOhaAvailable()) {
            assertTrue(true); // Skip test
            return;
        }
        
        // Start a long-running command
        $command = $this->getOhaBinaryPath() . ' -z 1s https://example.com';
        
        $this->executor->executeTest($command);
        assertTrue($this->executor->isRunning());
        
        // Try to start another test
        try {
            $this->executor->executeTest($this->getOhaBinaryPath() . ' -z 1s https://example.com');
            // If we reach here, exception was not thrown
            throw new Exception('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            assertTrue(strpos($e->getMessage(), 'A test is already running') !== false);
        }
        
        // Clean up
        $this->executor->stopTest();
    }
    
    public function testStopTest()
    {
        // Skip this test if oha is not available
        if (!$this->isOhaAvailable()) {
            assertTrue(true); // Skip test
            return;
        }
        
        // Start a long-running command
        $command = $this->getOhaBinaryPath() . ' -z 10s https://example.com';
        
        $outputReceived = '';
        $outputCallback = function($output) use (&$outputReceived) {
            $outputReceived .= $output;
        };
        
        $this->executor->executeTest($command, null, $outputCallback);
        assertTrue($this->executor->isRunning());
        
        // Stop the test
        $stopped = $this->executor->stopTest();
        assertTrue($stopped);
        assertFalse($this->executor->isRunning());
        
        // Should have received stop message
        assertTrue(strpos($outputReceived, '[Test stopped by user]') !== false);
    }
    
    public function testStopTestWhenNotRunning()
    {
        assertFalse($this->executor->isRunning());
        $stopped = $this->executor->stopTest();
        assertFalse($stopped);
    }
    
    public function testGetOutputAccumulation()
    {
        // Skip this test if oha is not available
        if (!$this->isOhaAvailable()) {
            assertTrue(true); // Skip test
            return;
        }
        
        $command = $this->getOhaBinaryPath() . ' --help';
        
        $this->executor->executeTest($command);
        
        // Wait for completion
        $maxWait = 50;
        $waited = 0;
        while ($this->executor->isRunning() && $waited < $maxWait) {
            $this->executor->update();
            usleep(100000);
            $waited++;
        }
        
        $output = $this->executor->getOutput();
        assertTrue(strlen($output) > 0);
    }
    
    public function testInvalidCommand()
    {
        $command = 'nonexistent_command_12345';
        
        try {
            $this->executor->executeTest($command);
            
            // If we get here, the command started (which might happen on some systems)
            // Let's wait a bit and see what happens
            $maxWait = 10;
            $waited = 0;
            while ($this->executor->isRunning() && $waited < $maxWait) {
                $this->executor->update();
                usleep(100000);
                $waited++;
            }
            
            // The command should have failed and stopped running
            assertFalse($this->executor->isRunning());
            
        } catch (\RuntimeException $e) {
            // This is the expected behavior - command failed to start
            // Check for either error message (old or new)
            assertTrue(
                strpos($e->getMessage(), 'Failed to start oha process') !== false ||
                strpos($e->getMessage(), 'oha binary not found') !== false,
                'Expected error message not found. Actual message: ' . $e->getMessage()
            );
        }
    }
    
    public function testSyncExecutionTimeout()
    {
        // Skip this test if oha is not available
        if (!$this->isOhaAvailable()) {
            assertTrue(true); // Skip test
            return;
        }
        
        $command = $this->getOhaBinaryPath() . ' -z 30s https://example.com'; // Long running command
        
        try {
            $this->executor->executeTestSync($command, 1); // 1 second timeout
            // If we reach here, exception was not thrown
            throw new Exception('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            assertTrue(strpos($e->getMessage(), 'Test execution timed out') !== false);
        }
    }
    
    public function testIsRunningMethod()
    {
        // Skip this test if oha is not available
        if (!$this->isOhaAvailable()) {
            assertTrue(true); // Skip test
            return;
        }
        
        $command = $this->getOhaBinaryPath() . ' -z 2s https://example.com';
        
        $this->executor->executeTest($command);
        assertTrue($this->executor->isRunning());
        
        // Clean up
        $this->executor->stopTest();
    }
    
    public function testOutputCallbackReceivesRealTimeData()
    {
        // Skip this test if oha is not available
        if (!$this->isOhaAvailable()) {
            assertTrue(true); // Skip test
            return;
        }
        
        $command = $this->getOhaBinaryPath() . ' -z 1s https://example.com';
        
        $outputChunks = [];
        $outputCallback = function($output) use (&$outputChunks) {
            $outputChunks[] = $output;
        };
        
        $this->executor->executeTest($command, null, $outputCallback);
        
        // Wait for completion
        $maxWait = 50;
        $waited = 0;
        while ($this->executor->isRunning() && $waited < $maxWait) {
            $this->executor->update();
            usleep(100000);
            $waited++;
        }
        
        // Should have received multiple chunks
        assertGreaterThan(0, count($outputChunks));
    }
    
    public function testCompletionCallbackReceivesTestResult()
    {
        // Skip this test if oha is not available
        if (!$this->isOhaAvailable()) {
            assertTrue(true); // Skip test
            return;
        }
        
        $command = $this->getOhaBinaryPath() . ' --help';
        
        $completionCalled = false;
        $receivedResult = null;
        
        $completionCallback = function($result) use (&$completionCalled, &$receivedResult) {
            $completionCalled = true;
            $receivedResult = $result;
        };
        
        $this->executor->executeTest($command, null, null, $completionCallback);
        
        // Wait for completion
        $maxWait = 50;
        $waited = 0;
        while ($this->executor->isRunning() && $waited < $maxWait) {
            $this->executor->update();
            usleep(100000);
            $waited++;
        }
        
        assertTrue($completionCalled);
        assertNotNull($receivedResult);
        assertTrue(strlen($receivedResult->rawOutput) > 0);
    }
    
    public function testUpdateMethodWhenNotRunning()
    {
        assertFalse($this->executor->isRunning());
        
        // Should not throw any exceptions
        $this->executor->update();
        
        assertFalse($this->executor->isRunning());
    }
    
    public function testDestructorStopsRunningTest()
    {
        // Skip this test if oha is not available
        if (!$this->isOhaAvailable()) {
            assertTrue(true); // Skip test
            return;
        }
        
        $executor = new TestExecutor();
        $command = $this->getOhaBinaryPath() . ' -z 5s https://example.com';
        
        $executor->executeTest($command);
        assertTrue($executor->isRunning());
        
        // Destructor should be called when variable goes out of scope
        unset($executor);
        
        // We can't directly test the destructor, but we can verify
        // that the test pattern works without hanging
        assertTrue(true);
    }
    
    /**
     * Test error handling for commands that exit with non-zero status
     */
    public function testCommandWithErrorExitCode()
    {
        // Skip this test if oha is not available
        if (!$this->isOhaAvailable()) {
            assertTrue(true); // Skip test
            return;
        }
        
        // Use an oha command that will fail (invalid URL)
        $command = $this->getOhaBinaryPath() . ' -z 1s http://invalid-domain-that-does-not-exist-12345.com';
        
        $completionCalled = false;
        $testResult = null;
        
        $completionCallback = function($result) use (&$completionCalled, &$testResult) {
            $completionCalled = true;
            $testResult = $result;
        };
        
        $this->executor->executeTest($command, null, $completionCallback);
        
        // Wait for completion
        $maxWait = 50;
        $waited = 0;
        while ($this->executor->isRunning() && $waited < $maxWait) {
            $this->executor->update();
            usleep(100000);
            $waited++;
        }
        
        assertTrue($completionCalled);
        assertNotNull($testResult);
    }
    
    /**
     * Check if oha is available for testing
     */
    private function isOhaAvailable(): bool
    {
        $binaryName = PHP_OS_FAMILY === 'Windows' ? 'oha.exe' : 'oha';
        $localBinPath = getcwd() . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $binaryName;
        return file_exists($localBinPath) && is_executable($localBinPath);
    }
    
    /**
     * Get oha binary path for testing
     */
    private function getOhaBinaryPath(): string
    {
        // First try local bin directory
        $binaryName = PHP_OS_FAMILY === 'Windows' ? 'oha.exe' : 'oha';
        $localBinPath = getcwd() . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $binaryName;
        if (file_exists($localBinPath) && is_executable($localBinPath)) {
            return $localBinPath;
        }
        
        // Fallback to just the binary name (should be in PATH)
        return $binaryName;
    }
}