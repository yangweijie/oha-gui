<?php

namespace OhaGui\Models;

use DateTime;
use Exception;

/**
 * TestConfiguration data model for storing HTTP load test parameters
 */
class TestConfiguration
{
    public string $name;
    public string $url;
    public string $method;
    public int $concurrentConnections;
    public int $duration;
    public int $timeout;
    public array $headers;
    public string $body;
    public DateTime $createdAt;
    public DateTime $updatedAt;

    public function __construct(
        string $name = '',
        string $url = '',
        string $method = 'GET',
        int $concurrentConnections = 1,
        int $duration = 2,
        int $timeout = 30,
        array $headers = [],
        string $body = ''
    ) {
        $this->name = $name;
        $this->url = $url;
        $this->method = $method;
        $this->concurrentConnections = $concurrentConnections;
        $this->duration = $duration;
        $this->timeout = $timeout;
        $this->headers = $headers;
        $this->body = $body;
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }

    /**
     * Convert configuration to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'url' => $this->url,
            'method' => $this->method,
            'concurrentConnections' => $this->concurrentConnections,
            'duration' => $this->duration,
            'timeout' => $this->timeout,
            'headers' => $this->headers,
            'body' => $this->body,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Create configuration from array data
     * @throws Exception
     */
    public static function fromArray(array $data): self
    {
        $config = new self(
            $data['name'] ?? '',
            $data['url'] ?? '',
            $data['method'] ?? 'GET',
            $data['concurrentConnections'] ?? 1,
            $data['duration'] ?? 2,
            $data['timeout'] ?? 30,
            $data['headers'] ?? [],
            $data['body'] ?? ''
        );

        if (isset($data['createdAt'])) {
            $config->createdAt = new DateTime($data['createdAt']);
        }
        if (isset($data['updatedAt'])) {
            $config->updatedAt = new DateTime($data['updatedAt']);
        }

        return $config;
    }

    /**
     * Validate configuration parameters
     * Returns array of validation errors, empty if valid
     */
    public function validate(): array
    {
        $errors = [];

        // Validate URL format
        if (empty($this->url)) {
            $errors[] = 'URL is required';
        } elseif (!filter_var($this->url, FILTER_VALIDATE_URL)) {
            $errors[] = 'URL format is invalid';
        }

        // Validate HTTP method
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
        if (!in_array(strtoupper($this->method), $validMethods)) {
            $errors[] = 'HTTP method must be one of: ' . implode(', ', $validMethods);
        }

        // Validate concurrent connections
        if ($this->concurrentConnections < 1 || $this->concurrentConnections > 1000) {
            $errors[] = 'Concurrent connections must be between 1 and 1000';
        }

        // Validate duration
        if ($this->duration < 1 || $this->duration > 3600) {
            $errors[] = 'Duration must be between 1 and 3600 seconds';
        }

        // Validate timeout
        if ($this->timeout < 1 || $this->timeout > 300) {
            $errors[] = 'Timeout must be between 1 and 300 seconds';
        }

        // Validate headers format
        if (!is_array($this->headers)) {
            $errors[] = 'Headers must be an array';
        } else {
            foreach ($this->headers as $key => $value) {
                if (!is_string($key) || !is_string($value)) {
                    $errors[] = 'Headers must be key-value pairs of strings';
                    break;
                }
            }
        }

        // Validate request body for JSON format if method supports body
        $methodsWithBody = ['POST', 'PUT', 'PATCH'];
        if (in_array(strtoupper($this->method), $methodsWithBody) && !empty($this->body)) {
            if (!$this->isValidJson($this->body) && !$this->isValidFormData($this->body)) {
                $errors[] = 'Request body must be valid JSON or form data';
            }
        }

        // Validate configuration name
        if (empty($this->name)) {
            $errors[] = 'Configuration name is required';
        } elseif (strlen($this->name) > 100) {
            $errors[] = 'Configuration name must be 100 characters or less';
        } elseif (!preg_match('/^[a-zA-Z0-9_\-\s]+$/', $this->name)) {
            $errors[] = 'Configuration name can only contain letters, numbers, spaces, hyphens, and underscores';
        }

        return $errors;
    }

    /**
     * Check if string is valid JSON
     */
    private function isValidJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Check if string is valid form data format
     */
    private function isValidFormData(string $string): bool
    {
        // Simple check for form data format (key=value&key=value)
        return preg_match('/^[^=&]+(=[^&]*)?(&[^=&]+(=[^&]*)?)*$/', $string) === 1;
    }

    /**
     * Update the updatedAt timestamp
     */
    public function touch(): void
    {
        $this->updatedAt = new DateTime();
    }
}