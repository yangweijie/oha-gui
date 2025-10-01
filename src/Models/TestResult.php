<?php

namespace OhaGui\Models;

use DateTime;

/**
 * TestResult data model for storing HTTP load test results
 */
class TestResult
{
    public float $requestsPerSecond;
    public int $totalRequests;
    public int $failedRequests;
    public float $successRate;
    public string $rawOutput;
    public DateTime $executedAt;

    public function __construct(
        float $requestsPerSecond = 0.0,
        int $totalRequests = 0,
        int $failedRequests = 0,
        float $successRate = 0.0,
        string $rawOutput = ''
    ) {
        $this->requestsPerSecond = $requestsPerSecond;
        $this->totalRequests = $totalRequests;
        $this->failedRequests = $failedRequests;
        $this->successRate = $successRate;
        $this->rawOutput = $rawOutput;
        $this->executedAt = new DateTime();
    }

    /**
     * Convert result to array for serialization
     */
    public function toArray(): array
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
     * Create result from array data
     */
    public static function fromArray(array $data): self
    {
        $result = new self(
            $data['requestsPerSecond'] ?? 0.0,
            $data['totalRequests'] ?? 0,
            $data['failedRequests'] ?? 0,
            $data['successRate'] ?? 0.0,
            $data['rawOutput'] ?? ''
        );

        if (isset($data['executedAt'])) {
            $result->executedAt = new DateTime($data['executedAt']);
        }

        return $result;
    }

    /**
     * Get formatted summary of test results
     */
    public function getFormattedSummary(): string
    {
        $summary = [];
        
        $summary[] = sprintf('Requests/sec: %.2f', $this->requestsPerSecond);
        $summary[] = sprintf('Total requests: %d', $this->totalRequests);
        
        if ($this->failedRequests > 0) {
            $summary[] = sprintf('Failed requests: %d', $this->failedRequests);
        }
        
        $summary[] = sprintf('Success rate: %.2f%%', $this->successRate);
        
        $successfulRequests = $this->totalRequests - $this->failedRequests;
        if ($successfulRequests > 0) {
            $summary[] = sprintf('Successful requests: %d', $successfulRequests);
        }
        
        $summary[] = sprintf('Executed at: %s', $this->executedAt->format('Y-m-d H:i:s'));

        return implode("\n", $summary);
    }

    /**
     * Get performance rating based on requests per second
     */
    public function getPerformanceRating(): string
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

    /**
     * Check if the test was successful (success rate >= 95%)
     */
    public function isSuccessful(): bool
    {
        return $this->successRate >= 95.0;
    }

    /**
     * Get average response time in milliseconds (calculated from requests per second)
     */
    public function getAverageResponseTime(): float
    {
        if ($this->requestsPerSecond <= 0) {
            return 0.0;
        }
        
        // Average response time = 1000ms / requests per second
        return 1000.0 / $this->requestsPerSecond;
    }

    /**
     * Get formatted metrics for display
     */
    public function getFormattedMetrics(): array
    {
        return [
            'requests_per_second' => number_format($this->requestsPerSecond, 2),
            'total_requests' => number_format($this->totalRequests),
            'failed_requests' => number_format($this->failedRequests),
            'success_rate' => number_format($this->successRate, 2) . '%',
            'performance_rating' => $this->getPerformanceRating(),
            'average_response_time' => number_format($this->getAverageResponseTime(), 2) . 'ms',
            'executed_at' => $this->executedAt->format('Y-m-d H:i:s')
        ];
    }
}