<?php

/**
 * Mock OHA binary for testing purposes
 * 
 * This script simulates the oha binary to test cross-platform functionality
 * without requiring the actual oha installation.
 */

if (count($argv) < 2) {
    echo "Mock OHA - HTTP Load Testing Tool (Mock Version)\n";
    echo "Usage: php mock_oha.php [options] <url>\n";
    echo "\nOptions:\n";
    echo "  --version    Show version information\n";
    echo "  -c <num>     Number of concurrent connections\n";
    echo "  -z <time>    Duration of test\n";
    echo "  -t <time>    Timeout per request\n";
    echo "  -m <method>  HTTP method\n";
    echo "  -H <header>  HTTP header\n";
    echo "  -d <data>    Request body data\n";
    echo "  --no-tui     Disable TUI\n";
    exit(0);
}

// Handle --version flag
if (in_array('--version', $argv)) {
    echo "oha 0.5.4 (mock version for testing)\n";
    exit(0);
}

// Parse basic arguments for simulation
$url = end($argv);
$concurrent = 1;
$duration = 10;
$method = 'GET';

// Simple argument parsing
for ($i = 1; $i < count($argv) - 1; $i++) {
    switch ($argv[$i]) {
        case '-c':
            $concurrent = (int)($argv[$i + 1] ?? 1);
            $i++;
            break;
        case '-z':
            $duration = (int)str_replace('s', '', $argv[$i + 1] ?? '10');
            $i++;
            break;
        case '-m':
            $method = $argv[$i + 1] ?? 'GET';
            $i++;
            break;
    }
}

// Simulate test execution
echo "Running mock load test...\n";
echo "URL: $url\n";
echo "Method: $method\n";
echo "Concurrent connections: $concurrent\n";
echo "Duration: {$duration}s\n";
echo "\n";

// Simulate progress
for ($i = 1; $i <= 5; $i++) {
    echo "Progress: " . ($i * 20) . "%\n";
    usleep(200000); // 0.2 seconds
}

// Simulate results output (matching the format expected by ResultParser)
$totalRequests = $concurrent * $duration * rand(8, 12);
$failedRequests = rand(0, (int)($totalRequests * 0.05));
$successfulRequests = $totalRequests - $failedRequests;
$successRate = ($successfulRequests / $totalRequests) * 100;
$requestsPerSecond = $totalRequests / $duration;

echo "\nSummary:\n";
echo "  Success rate: " . number_format($successRate, 2) . "%\n";
echo "  Total:        $totalRequests requests\n";
echo "  Slowest:      " . rand(100, 500) . " ms\n";
echo "  Fastest:      " . rand(10, 50) . " ms\n";
echo "  Average:      " . rand(50, 200) . " ms\n";
echo "  Requests/sec: " . number_format($requestsPerSecond, 2) . "\n";
echo "\nStatus code distribution:\n";
echo "  [200] $successfulRequests responses\n";

if ($failedRequests > 0) {
    echo "  [500] $failedRequests responses\n";
}

exit(0);