<?php

namespace OhaGui\Core;

use OhaGui\Models\TestResult;
use OhaGui\Models\TestConfiguration;
use DateTime;

/**
 * ResultParser - Parses oha command output and extracts performance metrics
 * 
 * This class implements regex patterns to parse oha output based on the tech.md
 * specifications and extracts key performance metrics like requests per second,
 * total requests, and success rate.
 */
class ResultParser
{
    /**
     * Parse oha output and create TestResult object
     * 
     * @param string $output The raw output from oha command
     * @param TestConfiguration|null $config The test configuration (optional)
     * @return TestResult Parsed test result with extracted metrics
     */
    public function parseOutput(string $output, ?TestConfiguration $config = null): TestResult
    {
        $testResult = new TestResult();
        $testResult->rawOutput = $output;
        $testResult->executedAt = new DateTime();
        
        // Extract metrics using regex patterns based on tech.md specifications
        $metrics = $this->extractMetrics($output);
        
        // If we have a configuration, we can calculate or get additional metrics
        if ($config !== null) {
            $metrics = $this->enhanceMetricsWithConfig($metrics, $config);
        }
        
        // Populate TestResult object with extracted metrics
        $testResult->requestsPerSecond = $metrics['requestsPerSecond'] ?? 0.0;
        $testResult->totalRequests = $metrics['totalRequests'] ?? 0;
        $testResult->failedRequests = $metrics['failedRequests'] ?? 0;
        $testResult->successRate = $metrics['successRate'] ?? 0.0;
        
        return $testResult;
    }
    
    /**
     * Extract performance metrics from oha output using regex patterns
     * 
     * Based on tech.md, oha output contains patterns like:
     * - "Requests/sec: 299.0098"
     * - "Success rate: 95.50%"
     * - Status code distribution with total requests count
     * - Error distribution with failed requests count
     * 
     * @param string $output The raw oha output
     * @return array Associative array with extracted metrics
     */
    private function extractMetrics(string $output): array
    {
        $metrics = [
            'requestsPerSecond' => 0.0,
            'totalRequests' => 0,
            'failedRequests' => 0,
            'successRate' => 0.0
        ];
        
        // Extract requests per second
        if (preg_match('/Requests\/sec:\s*(\d+(?:\.\d+)?)/i', $output, $matches)) {
            $metrics['requestsPerSecond'] = (float)$matches[1];
        }
        
        // Extract success rate
        if (preg_match('/Success rate:\s*(\d+(?:\.\d+)?)%/i', $output, $matches)) {
            $metrics['successRate'] = (float)$matches[1];
        }
        
        // Extract total requests from status code distribution
        // Look for patterns like "[200] 4579 responses"
        if (preg_match_all('/\[\d+\]\s*(\d+)\s+responses/', $output, $matches)) {
            $totalRequests = 0;
            foreach ($matches[1] as $count) {
                $totalRequests += (int)$count;
            }
            $metrics['totalRequests'] = $totalRequests;
        }
        
        // Extract failed requests from error distribution
        // Look for patterns like "[14] connection closed before message completed"
        // Only match lines in the "Error distribution" section
        if (preg_match('/Error distribution:\s*(.*?)\s*(?:\n\s*\n|$)/s', $output, $errorSection)) {
            if (preg_match_all('/^\s*\[(\d+)\]\s+.+$/m', $errorSection[1], $matches)) {
                $failedRequests = 0;
                foreach ($matches[1] as $count) {
                    $failedRequests += (int)$count;
                }
                $metrics['failedRequests'] = $failedRequests;
            }
        }
        
        // If we still don't have total requests, try alternative patterns
        if ($metrics['totalRequests'] == 0) {
            // Try to get total requests from "N requests" pattern
            if (preg_match('/(\d+)\s+requests/i', $output, $matches)) {
                $metrics['totalRequests'] = (int)$matches[1];
            }
        }
        
        // If we don't have failed requests from error distribution, 
        // calculate from success rate and total requests
        if ($metrics['failedRequests'] == 0 && $metrics['totalRequests'] > 0) {
            // Use more precise calculation for high precision success rates
            $successRate = $metrics['successRate'] / 100;
            $successfulRequests = (int)floor($metrics['totalRequests'] * $successRate + 0.5);
            $metrics['failedRequests'] = $metrics['totalRequests'] - $successfulRequests;
        }
        
        // Try alternative parsing patterns if we're missing key metrics
        if ($metrics['requestsPerSecond'] == 0.0 || $metrics['totalRequests'] == 0) {
            $alternativeMetrics = $this->tryAlternativePatterns($output);
            $metrics = array_merge($metrics, $alternativeMetrics);
        }
        
        return $metrics;
    }
    
    /**
     * Try alternative regex patterns for different oha output formats
     */
    private function tryAlternativePatterns(string $output): array
    {
        $metrics = [];
        
        // Pattern 1: Look for "Requests/sec" with various formats
        if (preg_match('/Requests?\/sec(?:ond)?:?\s*(\d+(?:\.\d+)?)/i', $output, $matches)) {
            $metrics['requestsPerSecond'] = (float)$matches[1];
        }
        
        // Pattern 2: Look for total requests in different formats
        if (preg_match('/(?:Total|Completed):?\s*(\d+)\s*(?:requests?|reqs?)/i', $output, $matches)) {
            $metrics['totalRequests'] = (int)$matches[1];
        }
        
        // Pattern 3: Look for success rate in different formats
        if (preg_match('/Success(?:\s+rate)?:?\s*(\d+(?:\.\d+)?)%/i', $output, $matches)) {
            $metrics['successRate'] = (float)$matches[1];
        }
        
        // Pattern 4: Look for failed requests directly
        if (preg_match('/(?:Failed|Error|Timeout):?\s*(\d+)/i', $output, $matches)) {
            $metrics['failedRequests'] = (int)$matches[1];
        }
        
        // Pattern 5: Look for response time statistics (additional info)
        if (preg_match('/Average:?\s*(\d+(?:\.\d+)?)\s*ms/i', $output, $matches)) {
            $metrics['averageResponseTime'] = (float)$matches[1];
        }
        
        return $metrics;
    }
    
    /**
     * Enhance metrics with configuration data
     * 
     * @param array $metrics The extracted metrics
     * @param TestConfiguration $config The test configuration
     * @return array Enhanced metrics
     */
    private function enhanceMetricsWithConfig(array $metrics, TestConfiguration $config): array
    {
        // For now, we just return the metrics as-is
        // In the future, we might add configuration-specific enhancements
        return $metrics;
    }
    
    /**
     * Get formatted summary of test results
     * 
     * @param TestResult $result The test result to format
     * @return string Formatted summary string
     */
    public function getFormattedSummary(TestResult $result): string
    {
        $summary = [];
        
        $summary[] = sprintf('Requests/sec: %.2f', $result->requestsPerSecond);
        $summary[] = sprintf('Total requests: %d', $result->totalRequests);
        
        if ($result->totalRequests > 0) {
            $summary[] = sprintf('Failed requests: %d', $result->failedRequests);
            $summary[] = sprintf('Success rate: %.2f%%', $result->successRate);
        }
        
        return implode("\n", $summary);
    }
    
    /**
     * Check if the output indicates a successful test completion
     * 
     * @param string $output The raw oha output
     * @return bool True if test completed successfully, false otherwise
     */
    public function isSuccessfulTest(string $output): bool
    {
        // Check for common success indicators in oha output
        $successIndicators = [
            '/Requests\/sec:\s*\d+/i',
            '/Total:\s*\d+\s+requests/i',
            '/Success rate:/i',
            '/Summary:/i'
        ];
        
        foreach ($successIndicators as $pattern) {
            if (preg_match($pattern, $output)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract error messages from oha output
     * 
     * @param string $output The raw oha output
     * @return array Array of error messages found in the output
     */
    public function extractErrors(string $output): array
    {
        $errors = [];
        
        // Common error patterns in oha output
        $errorPatterns = [
            '/Error:\s*(.+)/i',
            '/Failed to connect:\s*(.+)/i',
            '/Timeout:\s*(.+)/i',
            '/DNS resolution failed:\s*(.+)/i',
            '/Connection refused:\s*(.+)/i',
            '/SSL error:\s*(.+)/i'
        ];
        
        foreach ($errorPatterns as $pattern) {
            if (preg_match_all($pattern, $output, $matches)) {
                foreach ($matches[1] as $error) {
                    $errors[] = trim($error);
                }
            }
        }
        
        return array_unique($errors);
    }
    
    /**
     * Parse detailed statistics from oha output
     * 
     * @param string $output The raw oha output
     * @return array Detailed statistics including percentiles, response times, etc.
     */
    public function parseDetailedStats(string $output): array
    {
        $stats = [];
        
        // Parse response time percentiles - handle both "50%:" and "50% in" formats
        if (preg_match('/50%[:\s]*(?:in\s*)?(\d+(?:\.\d+)?)\s*(?:ms|secs?)/i', $output, $matches)) {
            $stats['p50_response_time'] = (float)$matches[1];
        }
        
        if (preg_match('/90%[:\s]*(?:in\s*)?(\d+(?:\.\d+)?)\s*(?:ms|secs?)/i', $output, $matches)) {
            $stats['p90_response_time'] = (float)$matches[1];
        }
        
        if (preg_match('/95%[:\s]*(?:in\s*)?(\d+(?:\.\d+)?)\s*(?:ms|secs?)/i', $output, $matches)) {
            $stats['p95_response_time'] = (float)$matches[1];
        }
        
        if (preg_match('/99%[:\s]*(?:in\s*)?(\d+(?:\.\d+)?)\s*(?:ms|secs?)/i', $output, $matches)) {
            $stats['p99_response_time'] = (float)$matches[1];
        }
        
        // Parse average response time
        if (preg_match('/Average:?\s*(\d+(?:\.\d+)?)\s*(?:ms|secs?)/i', $output, $matches)) {
            $stats['average_response_time'] = (float)$matches[1];
        }
        
        // Parse minimum response time
        if (preg_match('/Min:?\s*(\d+(?:\.\d+)?)\s*ms/i', $output, $matches)) {
            $stats['min_response_time'] = (float)$matches[1];
        }
        
        // Parse maximum response time
        if (preg_match('/Max:?\s*(\d+(?:\.\d+)?)\s*ms/i', $output, $matches)) {
            $stats['max_response_time'] = (float)$matches[1];
        }
        
        // Parse data transfer information
        if (preg_match('/Data transferred:?\s*(\d+(?:\.\d+)?)\s*([KMGT]?B)/i', $output, $matches)) {
            $stats['data_transferred'] = $matches[1] . ' ' . $matches[2];
        }
        
        // Parse transfer rate
        if (preg_match('/Transfer rate:?\s*(\d+(?:\.\d+)?)\s*([KMGT]?B\/sec)/i', $output, $matches)) {
            $stats['transfer_rate'] = $matches[1] . ' ' . $matches[2];
        }
        
        return $stats;
    }
    
    /**
     * Validate that the output is from oha command
     * 
     * @param string $output The output to validate
     * @return bool True if output appears to be from oha, false otherwise
     */
    public function isValidOhaOutput(string $output): bool
    {
        // Look for oha-specific patterns or headers
        $ohaIndicators = [
            '/oha\s+v?\d+/i',  // oha version info
            '/Requests\/sec:/i',
            '/Summary:/i',
            '/Status code distribution:/i'
        ];
        
        foreach ($ohaIndicators as $pattern) {
            if (preg_match($pattern, $output)) {
                return true;
            }
        }
        
        // If no specific indicators found, check if it has basic metrics
        return $this->isSuccessfulTest($output);
    }
}