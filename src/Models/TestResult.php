<?php

namespace OhaGui\Models;

use DateTime;

/**
 * Test Result data model
 * Represents the results of an HTTP load test execution
 */
class TestResult
{
    public $requestsPerSecond;
    public $totalRequests;
    public $failedRequests;
    public $successRate;
    public $rawOutput;
    public $executedAt;

    public function __construct(
        $requestsPerSecond = 0.0,
        $totalRequests = 0,
        $failedRequests = 0,
        $successRate = 0.0,
        $rawOutput = ''
    ) {
        $this->requestsPerSecond = $requestsPerSecond;
        $this->totalRequests = $totalRequests;
        $this->failedRequests = $failedRequests;
        $this->successRate = $successRate;
        $this->rawOutput = $rawOutput;
        $this->executedAt = new DateTime();
    }

    /**
     * Convert result to array format
     */
    public function toArray()
    {
        return [
            'requestsPerSecond' => $this->requestsPerSecond,
            'totalRequests' => $this->totalRequests,
            'failedRequests' => $this->failedRequests,
            'successRate' => $this->successRate,
            'rawOutput' => $this->rawOutput,
            'executedAt' => $this->executedAt->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Get formatted summary of test results
     */
    public function getFormattedSummary()
    {
        $summary = "Test Results Summary:\n";
        $summary .= "=====================\n";
        $summary .= sprintf("Requests per second: %.2f\n", $this->requestsPerSecond);
        $summary .= sprintf("Total requests: %d\n", $this->totalRequests);
        $summary .= sprintf("Failed requests: %d\n", $this->failedRequests);
        $summary .= sprintf("Success rate: %.2f%%\n", $this->successRate);
        $summary .= sprintf("Executed at: %s\n", $this->executedAt->format('Y-m-d H:i:s'));
        
        return $summary;
    }

    /**
     * Calculate success rate based on total and failed requests
     */
    public function calculateSuccessRate()
    {
        if ($this->totalRequests > 0) {
            $successfulRequests = $this->totalRequests - $this->failedRequests;
            $this->successRate = ($successfulRequests / $this->totalRequests) * 100;
        } else {
            $this->successRate = 0.0;
        }
    }

    /**
     * Check if the test was successful (no failures)
     */
    public function isSuccessful()
    {
        return $this->failedRequests === 0 && $this->totalRequests > 0;
    }

    /**
     * Get human-readable performance rating
     */
    public function getPerformanceRating()
    {
        if ($this->requestsPerSecond >= 1000) {
            return 'Excellent';
        } elseif ($this->requestsPerSecond >= 500) {
            return 'Good';
        } elseif ($this->requestsPerSecond >= 100) {
            return 'Average';
        } elseif ($this->requestsPerSecond >= 10) {
            return 'Poor';
        } else {
            return 'Very Poor';
        }
    }
}