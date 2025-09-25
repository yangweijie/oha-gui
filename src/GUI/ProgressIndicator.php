<?php

namespace OhaGui\GUI;

use Kingbes\Libui\Label;
use Kingbes\Libui\ProgressBar;

/**
 * Progress Indicator for Test Execution
 * 
 * Shows progress during oha test execution since oha doesn't provide real-time output
 */
class ProgressIndicator
{
    private $progressBar = null;
    private $statusLabel = null;
    private $timeLabel = null;
    private int $startTime = 0;
    private int $duration = 0;
    private bool $isActive = false;

    /**
     * Create progress indicator components
     * 
     * @return array Array containing [progressBar, statusLabel, timeLabel]
     */
    public function createComponents(): array
    {
        // Create progress bar (indeterminate style)
        $this->progressBar = ProgressBar::create();
        
        // Create status label
        $this->statusLabel = Label::create('Ready to start test...');
        
        // Create time label
        $this->timeLabel = Label::create('');
        
        return [
            'progressBar' => $this->progressBar,
            'statusLabel' => $this->statusLabel,
            'timeLabel' => $this->timeLabel
        ];
    }

    /**
     * Start progress indication
     * 
     * @param int $testDuration Duration of test in seconds
     * @param int $concurrentConnections Number of concurrent connections
     * @param string $url Target URL
     */
    public function startProgress(int $testDuration, int $concurrentConnections, string $url): void
    {
        $this->startTime = time();
        $this->duration = $testDuration;
        $this->isActive = true;
        
        // Update status
        $statusText = "🔄 Testing in progress...\n";
        $statusText .= "Target: {$url}\n";
        $statusText .= "Concurrent connections: {$concurrentConnections}\n";
        $statusText .= "Duration: {$testDuration} seconds\n";
        $statusText .= "⏳ Please wait for results...";
        
        if ($this->statusLabel) {
            Label::setText($this->statusLabel, $statusText);
        }
        
        // Set progress bar to indeterminate (pulsing)
        if ($this->progressBar) {
            ProgressBar::setValue($this->progressBar, -1); // Indeterminate mode
        }
        
        $this->updateTimeDisplay();
    }

    /**
     * Update progress during test execution
     * Should be called periodically
     */
    public function updateProgress(): void
    {
        if (!$this->isActive) {
            return;
        }
        
        $this->updateTimeDisplay();
        
        // If we know the duration, show progress percentage
        if ($this->duration > 0) {
            $elapsed = time() - $this->startTime;
            $progress = min(100, ($elapsed / $this->duration) * 100);
            
            if ($this->progressBar) {
                ProgressBar::setValue($this->progressBar, (int)$progress);
            }
        }
    }

    /**
     * Stop progress indication
     * 
     * @param bool $success Whether test completed successfully
     * @param string $message Optional completion message
     */
    public function stopProgress(bool $success = true, string $message = ''): void
    {
        $this->isActive = false;
        
        if ($this->progressBar) {
            ProgressBar::setValue($this->progressBar, $success ? 100 : 0);
        }
        
        $statusText = $success ? "✅ Test completed successfully!" : "❌ Test failed or was stopped";
        if ($message) {
            $statusText .= "\n" . $message;
        }
        
        if ($this->statusLabel) {
            Label::setText($this->statusLabel, $statusText);
        }
        
        // Show final time
        $this->updateTimeDisplay(true);
    }

    /**
     * Update time display
     * 
     * @param bool $final Whether this is the final time update
     */
    private function updateTimeDisplay(bool $final = false): void
    {
        if (!$this->timeLabel) {
            return;
        }
        
        $elapsed = time() - $this->startTime;
        
        if ($final) {
            $timeText = "⏱️ Total time: {$elapsed} seconds";
        } else {
            $remaining = max(0, $this->duration - $elapsed);
            $timeText = "⏱️ Elapsed: {$elapsed}s";
            
            if ($this->duration > 0) {
                $timeText .= " | Remaining: ~{$remaining}s";
            }
        }
        
        Label::setText($this->timeLabel, $timeText);
    }

    /**
     * Reset progress indicator
     */
    public function reset(): void
    {
        $this->isActive = false;
        $this->startTime = 0;
        $this->duration = 0;
        
        if ($this->progressBar) {
            ProgressBar::setValue($this->progressBar, 0);
        }
        
        if ($this->statusLabel) {
            Label::setText($this->statusLabel, 'Ready to start test...');
        }
        
        if ($this->timeLabel) {
            Label::setText($this->timeLabel, '');
        }
    }

    /**
     * Check if progress is active
     * 
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Get elapsed time
     * 
     * @return int Elapsed seconds
     */
    public function getElapsedTime(): int
    {
        return $this->isActive ? (time() - $this->startTime) : 0;
    }

    /**
     * Get estimated remaining time
     * 
     * @return int Remaining seconds (0 if unknown)
     */
    public function getRemainingTime(): int
    {
        if (!$this->isActive || $this->duration <= 0) {
            return 0;
        }
        
        return max(0, $this->duration - $this->getElapsedTime());
    }
}