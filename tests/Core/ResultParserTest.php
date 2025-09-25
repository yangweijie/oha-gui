<?php

use OhaGui\Core\ResultParser;
use OhaGui\Models\TestResult;

/**
 * Unit tests for ResultParser class
 */
class ResultParserTest
{
    private $parser;

    public function setUp()
    {
        $this->parser = new ResultParser();
    }

    public function testParseBasicOhaOutput()
    {
        $sampleOutput = "
Summary:
  Success rate: 100.00%
  Total: 1000 requests
  Slowest: 0.0234 secs
  Fastest: 0.0012 secs
  Average: 0.0089 secs
  Requests/sec: 299.0098
        ";

        $result = $this->parser->parseOutput($sampleOutput);

        assertNotNull($result, 'Result should not be null');
        assertEquals(299.0098, $result->requestsPerSecond, 'Requests per second should match');
        assertEquals(1000, $result->totalRequests, 'Total requests should match');
        assertEquals(0, $result->failedRequests, 'Failed requests should be 0 for 100% success');
        assertEquals(100.0, $result->successRate, 'Success rate should be 100%');
        assertEquals($sampleOutput, $result->rawOutput, 'Raw output should be preserved');
    }

    public function testParseOhaOutputWithFailures()
    {
        $sampleOutput = "
Summary:
  Success rate: 95.50%
  Total: 2000 requests
  Slowest: 0.1234 secs
  Fastest: 0.0012 secs
  Average: 0.0234 secs
  Requests/sec: 450.25
        ";

        $result = $this->parser->parseOutput($sampleOutput);

        assertEquals(450.25, $result->requestsPerSecond, 'Requests per second should match');
        assertEquals(2000, $result->totalRequests, 'Total requests should match');
        assertEquals(90, $result->failedRequests, 'Failed requests should be calculated from success rate');
        assertEquals(95.5, $result->successRate, 'Success rate should match');
    }

    public function testParseAlternativeOhaFormat()
    {
        $sampleOutput = "
Statistics        Avg      Stdev        Max
  Reqs/sec      123.45      12.34     150.00
  Latency       45.67ms     5.67ms    78.90ms

Total: 500 requests
Success rate: 98.00%
        ";

        $result = $this->parser->parseOutput($sampleOutput);

        assertEquals(123.45, $result->requestsPerSecond, 'Should parse alternative RPS format');
        assertEquals(500, $result->totalRequests, 'Should parse total requests');
        assertEquals(98.0, $result->successRate, 'Should parse success rate');
        assertEquals(10, $result->failedRequests, 'Should calculate failed requests');
    }

    public function testParseEmptyOutput()
    {
        $result = $this->parser->parseOutput('');

        assertEquals(0.0, $result->requestsPerSecond, 'Empty output should have 0 RPS');
        assertEquals(0, $result->totalRequests, 'Empty output should have 0 total requests');
        assertEquals(0, $result->failedRequests, 'Empty output should have 0 failed requests');
        assertEquals(0.0, $result->successRate, 'Empty output should have 0% success rate');
    }

    public function testParsePartialOutput()
    {
        $sampleOutput = "
Some random output
Requests/sec: 75.5
More random text
        ";

        $result = $this->parser->parseOutput($sampleOutput);

        assertEquals(75.5, $result->requestsPerSecond, 'Should extract RPS from partial output');
        assertEquals(0, $result->totalRequests, 'Should default to 0 for missing total');
        assertEquals(0.0, $result->successRate, 'Should default to 0% for missing success rate');
    }

    public function testParseAdditionalMetrics()
    {
        $sampleOutput = "
Summary:
  Success rate: 100.00%
  Total: 1000 requests
  Average: 45.67 ms
  50%: 23.45 ms
  90%: 78.90 ms
  95%: 89.12 ms
  99%: 123.45 ms
  Data transfer: 1.5 MB/s
        ";

        $additionalMetrics = $this->parser->parseAdditionalMetrics($sampleOutput);

        assertEquals(45.67, $additionalMetrics['averageResponseTime'], 'Should parse average response time');
        assertEquals(23.45, $additionalMetrics['latency50%'], 'Should parse 50th percentile');
        assertEquals(78.90, $additionalMetrics['latency90%'], 'Should parse 90th percentile');
        assertEquals(89.12, $additionalMetrics['latency95%'], 'Should parse 95th percentile');
        assertEquals(123.45, $additionalMetrics['latency99%'], 'Should parse 99th percentile');
        assertEquals(1536.0, $additionalMetrics['dataTransferRate'], 'Should parse and convert data transfer rate to KB/s');
    }

    public function testFormatResultsForDisplay()
    {
        $result = new TestResult(299.5, 1000, 50, 95.0, 'raw output');
        
        $formatted = $this->parser->formatResultsForDisplay($result);

        assertTrue(strpos($formatted, 'Test Results:') !== false, 'Should contain header');
        assertTrue(strpos($formatted, '299.50') !== false, 'Should contain formatted RPS');
        assertTrue(strpos($formatted, '1000') !== false, 'Should contain total requests');
        assertTrue(strpos($formatted, '50') !== false, 'Should contain failed requests');
        assertTrue(strpos($formatted, '95.00%') !== false, 'Should contain success rate');
    }

    public function testGetSummary()
    {
        $result = new TestResult(1234.56, 5000, 100, 98.0, 'raw output');
        
        $summary = $this->parser->getSummary($result);

        assertEquals('1,234.56', $summary['rps'], 'Should format RPS with commas');
        assertEquals('5,000', $summary['total'], 'Should format total with commas');
        assertEquals('100', $summary['failed'], 'Should format failed requests');
        assertEquals('98.00%', $summary['success_rate'], 'Should format success rate with percentage');
        assertNotEmpty($summary['executed_at'], 'Should include execution time');
    }

    public function testIsValidOhaOutput()
    {
        $validOutputs = [
            'oha v0.5.4',
            'Requests/sec: 123.45',
            'Success rate: 95.5%',
            'Total: 1000 requests'
        ];

        foreach ($validOutputs as $output) {
            assertTrue($this->parser->isValidOhaOutput($output), "Should recognize valid oha output: $output");
        }

        $invalidOutputs = [
            'curl: command not found',
            'HTTP/1.1 200 OK',
            'Random text without oha indicators'
        ];

        foreach ($invalidOutputs as $output) {
            assertFalse($this->parser->isValidOhaOutput($output), "Should reject invalid output: $output");
        }
    }

    public function testParseErrors()
    {
        $sampleOutput = "
Summary:
  Success rate: 90.00%
  Total: 1000 requests
  Connection errors: 25
  Timeout errors: 15
  Read errors: 10
  Write errors: 0
        ";

        $errors = $this->parser->parseErrors($sampleOutput);

        assertEquals(25, $errors['connection_errors'], 'Should parse connection errors');
        assertEquals(15, $errors['timeout_errors'], 'Should parse timeout errors');
        assertEquals(10, $errors['read_errors'], 'Should parse read errors');
        assertEquals(0, $errors['write_errors'], 'Should parse write errors');
    }

    public function testParseRequestsPerSecondAlternativeFormats()
    {
        $testCases = [
            'Requests/sec: 123.45' => 123.45,
            '456.78 requests/sec' => 456.78,
            'RPS: 789.01' => 789.01,
            'req/s: 234.56' => 234.56
        ];

        foreach ($testCases as $output => $expected) {
            $result = $this->parser->parseOutput($output);
            assertEquals($expected, $result->requestsPerSecond, "Should parse RPS from: $output");
        }
    }

    public function testParseTotalRequestsAlternativeFormats()
    {
        $testCases = [
            'Total: 1000 requests' => 1000,
            '2500 total requests' => 2500,
            'Completed 750 requests' => 750,
            '1200 requests completed' => 1200
        ];

        foreach ($testCases as $output => $expected) {
            $result = $this->parser->parseOutput($output);
            assertEquals($expected, $result->totalRequests, "Should parse total requests from: $output");
        }
    }

    public function testParseSuccessRateAlternativeFormats()
    {
        $testCases = [
            'Success rate: 95.5%' => 95.5,
            '98.2% success' => 98.2,
            'Success: 100.0%' => 100.0
        ];

        foreach ($testCases as $output => $expected) {
            $result = $this->parser->parseOutput($output);
            assertEquals($expected, $result->successRate, "Should parse success rate from: $output");
        }
    }

    public function testCalculateSuccessRateFromErrors()
    {
        $sampleOutput = "
Total: 1000 requests
50 errors
        ";

        $result = $this->parser->parseOutput($sampleOutput);

        assertEquals(1000, $result->totalRequests, 'Should parse total requests');
        assertEquals(95.0, $result->successRate, 'Should calculate success rate from errors');
    }

    public function testDefaultSuccessRateForSuccessfulRequests()
    {
        $sampleOutput = "
Total: 500 requests
Requests/sec: 100.0
        ";

        $result = $this->parser->parseOutput($sampleOutput);

        assertEquals(500, $result->totalRequests, 'Should parse total requests');
        assertEquals(100.0, $result->successRate, 'Should default to 100% success when no errors mentioned');
    }
}