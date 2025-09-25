<?php

namespace OhaGui\Core;

use OhaGui\Models\TestResult;
use DateTime;

/**
 * Result Parser
 * Parses oha output and extracts performance metrics
 */
class ResultParser
{
    /**
     * Parse oha output and create TestResult object
     * 
     * @param string $output Raw oha output
     * @return TestResult Parsed test result
     */
    public function parseOutput($output)
    {
        $metrics = $this->extractMetrics($output);
        
        $result = new TestResult();
        $result->requestsPerSecond = $metrics['requestsPerSecond'] ?? 0.0;
        $result->totalRequests = $metrics['totalRequests'] ?? 0;
        $result->failedRequests = $metrics['failedRequests'] ?? 0;
        $result->successRate = $metrics['successRate'] ?? 0.0;
        $result->rawOutput = $output;
        $result->executedAt = new DateTime();
        
        return $result;
    }

    /**
     * Extract key metrics from oha output using regex patterns
     * Based on tech.md specifications
     * 
     * @param string $output Raw oha output
     * @return array Extracted metrics
     */
    private function extractMetrics($output)
    {
        $metrics = [
            'requestsPerSecond' => 0.0,
            'totalRequests' => 0,
            'failedRequests' => 0,
            'successRate' => 0.0
        ];

        // Parse using the regex pattern from tech.md
        preg_match_all('/Success rate:\s*(\d+\.\d+)%|Total:\s*(\d+)\s+requests|Requests\/sec:\s*(\d+\.\d+)/im', $output, $matches);
        
        // Extract success rate
        if (!empty($matches[1])) {
            foreach ($matches[1] as $match) {
                if (!empty($match)) {
                    $metrics['successRate'] = (float)$match;
                    break;
                }
            }
        }
        
        // Extract total requests
        if (!empty($matches[2])) {
            foreach ($matches[2] as $match) {
                if (!empty($match)) {
                    $metrics['totalRequests'] = (int)$match;
                    break;
                }
            }
        }
        
        // Extract requests per second
        if (!empty($matches[3])) {
            foreach ($matches[3] as $match) {
                if (!empty($match)) {
                    $metrics['requestsPerSecond'] = (float)$match;
                    break;
                }
            }
        }

        // Calculate failed requests from success rate and total requests
        if ($metrics['totalRequests'] > 0 && $metrics['successRate'] < 100.0) {
            $successfulRequests = (int)($metrics['totalRequests'] * ($metrics['successRate'] / 100.0));
            $metrics['failedRequests'] = $metrics['totalRequests'] - $successfulRequests;
        }

        // Try alternative parsing patterns for different oha output formats
        if ($metrics['requestsPerSecond'] == 0.0) {
            $metrics['requestsPerSecond'] = $this->parseRequestsPerSecond($output);
        }
        
        if ($metrics['totalRequests'] == 0) {
            $metrics['totalRequests'] = $this->parseTotalRequests($output);
        }
        
        if ($metrics['successRate'] == 0.0) {
            $metrics['successRate'] = $this->parseSuccessRate($output);
        }

        return $metrics;
    }

    /**
     * Parse requests per second with alternative patterns
     * 
     * @param string $output Raw output
     * @return float Requests per second
     */
    private function parseRequestsPerSecond($output)
    {
        // Try different patterns for requests per second
        $patterns = [
            '/Requests\/sec:\s*(\d+\.?\d*)/i',
            '/(\d+\.?\d*)\s*requests?\/sec/i',
            '/RPS:\s*(\d+\.?\d*)/i',
            '/req\/s:\s*(\d+\.?\d*)/i',
            '/Reqs\/sec\s+(\d+\.?\d*)/i'  // Added pattern for "Reqs/sec      123.45"
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $output, $matches)) {
                return (float)$matches[1];
            }
        }
        
        return 0.0;
    }

    /**
     * Parse total requests with alternative patterns
     * 
     * @param string $output Raw output
     * @return int Total requests
     */
    private function parseTotalRequests($output)
    {
        // Try different patterns for total requests
        $patterns = [
            '/Total:\s*(\d+)\s*requests?/i',
            '/(\d+)\s*total\s*requests?/i',
            '/Completed\s*(\d+)\s*requests?/i',
            '/(\d+)\s*requests?\s*completed/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $output, $matches)) {
                return (int)$matches[1];
            }
        }
        
        return 0;
    }

    /**
     * Parse success rate with alternative patterns
     * 
     * @param string $output Raw output
     * @return float Success rate percentage
     */
    private function parseSuccessRate($output)
    {
        // Try different patterns for success rate
        $patterns = [
            '/Success rate:\s*(\d+\.?\d*)%/i',
            '/(\d+\.?\d*)%\s*success/i',
            '/Success:\s*(\d+\.?\d*)%/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $output, $matches)) {
                return (float)$matches[1];
            }
        }
        
        // If no explicit success rate, try to calculate from error information
        if (preg_match('/(\d+)\s*errors?/i', $output, $errorMatches)) {
            $errors = (int)$errorMatches[1];
            $total = $this->parseTotalRequests($output);
            
            if ($total > 0) {
                return ((float)($total - $errors) / $total) * 100.0;
            }
        }
        
        // Default to 100% if no errors found and we have successful requests
        if ($this->parseTotalRequests($output) > 0) {
            return 100.0;
        }
        
        return 0.0;
    }

    /**
     * Parse additional metrics like response times, latency percentiles
     * 
     * @param string $output Raw output
     * @return array Additional metrics
     */
    public function parseAdditionalMetrics($output)
    {
        $metrics = [];
        
        // Parse average response time
        if (preg_match('/Average:\s*(\d+\.?\d*)\s*(ms|s)/i', $output, $matches)) {
            $time = (float)$matches[1];
            $unit = strtolower($matches[2]);
            
            // Convert to milliseconds
            if ($unit === 's') {
                $time *= 1000;
            }
            
            $metrics['averageResponseTime'] = $time;
        }
        
        // Parse latency percentiles
        $percentilePatterns = [
            '50%' => '/50%.*?(\d+\.?\d*)\s*(ms|s)/i',
            '90%' => '/90%.*?(\d+\.?\d*)\s*(ms|s)/i',
            '95%' => '/95%.*?(\d+\.?\d*)\s*(ms|s)/i',
            '99%' => '/99%.*?(\d+\.?\d*)\s*(ms|s)/i'
        ];
        
        foreach ($percentilePatterns as $percentile => $pattern) {
            if (preg_match($pattern, $output, $matches)) {
                $time = (float)$matches[1];
                $unit = strtolower($matches[2]);
                
                // Convert to milliseconds
                if ($unit === 's') {
                    $time *= 1000;
                }
                
                $metrics['latency' . $percentile] = $time;
            }
        }
        
        // Parse data transfer information
        if (preg_match('/(\d+\.?\d*)\s*(KB|MB|GB)\/s/i', $output, $matches)) {
            $rate = (float)$matches[1];
            $unit = strtoupper($matches[2]);
            
            // Convert to KB/s
            switch ($unit) {
                case 'MB':
                    $rate *= 1024;
                    break;
                case 'GB':
                    $rate *= 1024 * 1024;
                    break;
            }
            
            $metrics['dataTransferRate'] = $rate;
        }
        
        return $metrics;
    }

    /**
     * Format metrics for display
     * 
     * @param TestResult $result Test result to format
     * @return string Formatted display string
     */
    public function formatResultsForDisplay($result)
    {
        $output = "Test Results:\n";
        $output .= "=============\n\n";
        
        $output .= sprintf("Requests per second: %.2f\n", $result->requestsPerSecond);
        $output .= sprintf("Total requests: %d\n", $result->totalRequests);
        $output .= sprintf("Failed requests: %d\n", $result->failedRequests);
        $output .= sprintf("Success rate: %.2f%%\n", $result->successRate);
        
        if ($result->totalRequests > 0) {
            $avgTime = $result->totalRequests / max($result->requestsPerSecond, 0.001);
            $output .= sprintf("Average time per request: %.2f ms\n", $avgTime);
        }
        
        $output .= sprintf("Test executed at: %s\n", $result->executedAt->format('Y-m-d H:i:s'));
        
        return $output;
    }

    /**
     * Get a summary of key metrics
     * 
     * @param TestResult $result Test result
     * @return array Summary metrics
     */
    public function getSummary($result)
    {
        return [
            'rps' => number_format($result->requestsPerSecond, 2),
            'total' => number_format($result->totalRequests),
            'failed' => number_format($result->failedRequests),
            'success_rate' => number_format($result->successRate, 2) . '%',
            'executed_at' => $result->executedAt->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Validate if output appears to be from oha
     * 
     * @param string $output Raw output to validate
     * @return bool True if output appears to be from oha
     */
    public function isValidOhaOutput($output)
    {
        // Check for common oha output patterns - be very specific
        $ohaIndicators = [
            '/^oha\s+v\d/i',  // oha version at start of line
            '/Requests\/sec:\s*\d/i',  // Requests/sec with number
            '/Reqs\/sec\s+\d/i',       // Reqs/sec with number
            '/Success rate:\s*\d/i',   // Success rate with number
            '/Total:\s*\d+\s+requests/i'  // Total: number requests
        ];
        
        foreach ($ohaIndicators as $pattern) {
            if (preg_match($pattern, $output)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Extract error information from output
     * 
     * @param string $output Raw output
     * @return array Error information
     */
    public function parseErrors($output)
    {
        $errors = [
            'connection_errors' => 0,
            'timeout_errors' => 0,
            'read_errors' => 0,
            'write_errors' => 0
        ];
        
        // Common error patterns - more specific matching
        $errorPatterns = [
            'connection_errors' => '/Connection errors:\s*(\d+)/i',
            'timeout_errors' => '/Timeout errors:\s*(\d+)/i',
            'read_errors' => '/Read errors:\s*(\d+)/i',
            'write_errors' => '/Write errors:\s*(\d+)/i'
        ];
        
        foreach ($errorPatterns as $type => $pattern) {
            if (preg_match($pattern, $output, $matches)) {
                $errors[$type] = (int)$matches[1];
            }
        }
        
        return $errors;
    }
}