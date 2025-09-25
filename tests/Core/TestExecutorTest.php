<?php

use OhaGui\Core\TestExecutor;

/**
 * Unit tests for TestExecutor class
 */
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

    public function testInitialState(): void
    {
        assertFalse($this->executor->isRunning(), 'Executor should not be running initially');
        assertEquals('', $this->executor->getOutput(), 'Initial output should be empty');
        assertEquals(0, $this->executor->getExitCode(), 'Initial exit code should be 0');
    }

    public function testExecuteSimpleCommand(): void
    {
        // Use a simple command that should work on all platforms
        $command = 'echo "Hello World"';
        
        $outputReceived = '';
        $callbackCalled = false;
        
        $result = $this->executor->executeTest($command, function($output) use (&$outputReceived, &$callbackCalled) {
            $outputReceived .= $output;
            $callbackCalled = true;
        });
        
        assertTrue($result, 'Command should start successfully');
        assertTrue($this->executor->isRunning(), 'Executor should be running after starting command');
        
        // Wait for completion
        $completed = $this->executor->waitForCompletion(5);
        assertTrue($completed, 'Command should complete within timeout');
        
        assertFalse($this->executor->isRunning(), 'Executor should not be running after completion');
        assertTrue($callbackCalled, 'Output callback should have been called');
        assertTrue(strpos($outputReceived, 'Hello World') !== false, 'Output should contain expected text');
    }

    public function testExecuteTestSync(): void
    {
        // Use a simple command that should work on all platforms
        $command = 'echo "Sync Test"';
        
        $result = $this->executor->executeTestSync($command, 5);
        
        assertTrue($result['success'], 'Synchronous execution should succeed');
        assertEquals(0, $result['exitCode'], 'Exit code should be 0 for successful command');
        assertTrue(strpos($result['output'], 'Sync Test') !== false, 'Output should contain expected text');
    }

    public function testStopRunningTest(): void
    {
        // Use a long-running command for testing stop functionality
        $command = $this->getLongRunningCommand();
        
        $this->executor->executeTest($command);
        assertTrue($this->executor->isRunning(), 'Test should be running');
        
        $stopped = $this->executor->stopTest();
        assertTrue($stopped, 'Test should be stopped successfully');
        assertFalse($this->executor->isRunning(), 'Test should not be running after stop');
    }

    public function testCannotStartMultipleTests(): void
    {
        $command = $this->getLongRunningCommand();
        
        // Start first test
        $this->executor->executeTest($command);
        assertTrue($this->executor->isRunning(), 'First test should be running');
        
        // Try to start second test
        try {
            $this->executor->executeTest($command);
            assertTrue(false, 'Should have thrown exception for multiple tests');
        } catch (Exception $e) {
            assertTrue(strpos($e->getMessage(), 'already running') !== false, 'Exception should mention already running');
        }
        
        // Clean up
        $this->executor->stopTest();
    }

    public function testOutputCallback(): void
    {
        $command = 'echo "Line 1" && echo "Line 2"';
        
        $outputLines = [];
        $this->executor->executeTest($command, function($output) use (&$outputLines) {
            $outputLines[] = trim($output);
        });
        
        $this->executor->waitForCompletion(5);
        
        assertNotEmpty($outputLines, 'Should have received output lines');
        
        $allOutput = implode('', $outputLines);
        assertTrue(strpos($allOutput, 'Line 1') !== false, 'Should contain Line 1');
        assertTrue(strpos($allOutput, 'Line 2') !== false, 'Should contain Line 2');
    }

    public function testErrorCallback(): void
    {
        // Command that writes to stderr
        $command = $this->getErrorCommand();
        
        $errorReceived = '';
        $errorCallbackCalled = false;
        
        $this->executor->executeTest(
            $command,
            null, // no output callback
            function($error) use (&$errorReceived, &$errorCallbackCalled) {
                $errorReceived .= $error;
                $errorCallbackCalled = true;
            }
        );
        
        $this->executor->waitForCompletion(5);
        
        assertTrue($errorCallbackCalled, 'Error callback should have been called');
        assertNotEmpty($errorReceived, 'Should have received error output');
    }

    public function testCompletionCallback(): void
    {
        $command = 'echo "Test Complete"';
        
        $completionCalled = false;
        $receivedExitCode = null;
        
        $this->executor->executeTest(
            $command,
            null, // no output callback
            null, // no error callback
            function($exitCode) use (&$completionCalled, &$receivedExitCode) {
                $completionCalled = true;
                $receivedExitCode = $exitCode;
            }
        );
        
        $this->executor->waitForCompletion(5);
        
        assertTrue($completionCalled, 'Completion callback should have been called');
        assertEquals(0, $receivedExitCode, 'Exit code should be 0 for successful command');
    }

    public function testGetProcessStatus(): void
    {
        $command = $this->getLongRunningCommand();
        
        // Initially no process
        assertNull($this->executor->getProcessStatus(), 'Should return null when no process');
        
        $this->executor->executeTest($command);
        
        $status = $this->executor->getProcessStatus();
        assertNotNull($status, 'Should return status when process is running');
        assertIsArray($status, 'Status should be an array');
        assertTrue($status['running'], 'Process should be marked as running');
        
        $this->executor->stopTest();
        
        // After stopping, status should be null
        assertNull($this->executor->getProcessStatus(), 'Should return null after process cleanup');
    }

    public function testWaitForCompletionTimeout(): void
    {
        $command = $this->getLongRunningCommand();
        
        $this->executor->executeTest($command);
        
        // Wait with very short timeout
        $completed = $this->executor->waitForCompletion(1);
        assertFalse($completed, 'Should timeout for long-running command');
        
        // Clean up
        $this->executor->stopTest();
    }

    public function testSyncExecutionTimeout(): void
    {
        $command = $this->getLongRunningCommand();
        
        try {
            $this->executor->executeTestSync($command, 1);
            assertTrue(false, 'Should have thrown timeout exception');
        } catch (Exception $e) {
            assertTrue(strpos($e->getMessage(), 'timed out') !== false, 'Exception should mention timeout');
        }
    }

    public function testInvalidCommand(): void
    {
        $command = 'nonexistentcommand12345';
        
        try {
            $this->executor->executeTest($command);
            // Command might start but fail quickly, so wait a bit
            usleep(500000); // 500ms
            $this->executor->isRunning(); // This will check status and update exit code
            
            // The command should have failed
            assertNotEquals(0, $this->executor->getExitCode(), 'Invalid command should have non-zero exit code');
        } catch (Exception $e) {
            // It's also acceptable for the command to fail to start
            assertTrue(strpos($e->getMessage(), 'Failed to start') !== false, 'Exception should mention start failure');
        }
    }

    /**
     * Get a long-running command for testing
     */
    private function getLongRunningCommand(): string
    {
        // Use a command that works on both Windows and Unix
        if (PHP_OS_FAMILY === 'Windows') {
            return 'ping -n 10 127.0.0.1'; // Ping 10 times
        } else {
            return 'sleep 5'; // Sleep for 5 seconds
        }
    }

    /**
     * Get a command that writes to stderr
     */
    private function getErrorCommand(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return 'echo Error Message 1>&2'; // Redirect to stderr
        } else {
            return 'echo "Error Message" >&2'; // Redirect to stderr
        }
    }
}