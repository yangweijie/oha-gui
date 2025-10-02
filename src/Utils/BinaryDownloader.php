<?php

namespace OhaGui\Utils;

/**
 * Binary downloader utility for downloading oha binary based on OS and architecture
 */
class BinaryDownloader
{
    /**
     * Binary download URLs for different platforms
     * 
     * @var array
     */
    private const DOWNLOAD_URLS = [
        'linux' => [
            'x64' => 'https://xget.xi-xu.me/gh/hatoo/oha/releases/download/v1.10.0/oha-linux-amd64-pgo',
            'arm64' => 'https://xget.xi-xu.me/gh/hatoo/oha/releases/download/v1.10.0/oha-linux-arm64',
        ],
        'macos' => [
            'x64' => 'https://xget.xi-xu.me/gh/hatoo/oha/releases/download/v1.10.0/oha-macos-amd64',
            'arm64' => 'https://xget.xi-xu.me/gh/hatoo/oha/releases/download/v1.10.0/oha-macos-arm64',
        ],
        'windows' => [
            'x64' => 'https://xget.xi-xu.me/gh/hatoo/oha/releases/download/v1.10.0/oha-windows-amd64-pgo.exe',
        ],
    ];

    /**
     * Download the appropriate oha binary for the current system
     * 
     * @param string|null $targetDirectory Directory to download the binary to
     * @return string|null Path to downloaded binary or null on failure
     */
    public function downloadBinary(?string $targetDirectory = null): ?string
    {
        // Determine target directory
        if ($targetDirectory === null) {
            $targetDirectory = CrossPlatform::joinPath(CrossPlatform::getProjectRoot() ?? __DIR__, 'bin');
        }

        // Ensure target directory exists
        if (!is_dir($targetDirectory)) {
            if (!mkdir($targetDirectory, 0755, true)) {
                fwrite(STDERR, "Error: Failed to create directory: $targetDirectory\n");
                return null;
            }
        }

        // Get system information
        $os = CrossPlatform::getOperatingSystem();
        $arch = $this->getSystemArchitecture();

        // Check if x86 architecture (not supported)
        if ($arch === 'x86') {
            fwrite(STDERR, "Error: x86 architecture is not supported. Please use x64 system.\n");
            return null;
        }

        // Get download URL
        $downloadUrl = $this->getDownloadUrl($os, $arch);
        if ($downloadUrl === null) {
            fwrite(STDERR, "Error: No binary available for OS: $os, Architecture: $arch\n");
            return null;
        }

        // Determine binary name
        $binaryName = 'oha' . CrossPlatform::getExecutableExtension();
        $targetPath = CrossPlatform::joinPath($targetDirectory, $binaryName);

        // Download the binary
        echo "Downloading oha binary for $os ($arch)...
";
        echo "From: $downloadUrl
";
        echo "To: $targetPath
";

        if ($this->downloadFile($downloadUrl, $targetPath)) {
            // Make the binary executable (not needed on Windows)
            if (!CrossPlatform::isWindows() && !chmod($targetPath, 0755)) {
                fwrite(STDERR, "Warning: Failed to make binary executable: $targetPath\n");
            }

            echo "Download completed successfully!\n";
            return $targetPath;
        }

        return null;
    }

    /**
     * Get the system architecture
     * 
     * @return string
     */
    private function getSystemArchitecture(): string
    {
        // Get architecture from PHP
        $arch = php_uname('m');
        
        // Normalize architecture names
        switch (strtolower($arch)) {
            case 'x86_64':
            case 'amd64':
                return 'x64';
            case 'aarch64':
            case 'arm64':
                return 'arm64';
            case 'i386':
            case 'i686':
                return 'x86';
            default:
                // Try to detect with shell commands
                if (CrossPlatform::isWindows()) {
                    // On Windows, use systeminfo to get architecture
                    $output = shell_exec('systeminfo | findstr /C:"System Type"');
                    if (strpos($output, 'x64-based') !== false) {
                        return 'x64';
                    } elseif (strpos($output, 'ARM64') !== false) {
                        return 'arm64';
                    } else {
                        return 'x86';
                    }
                } else {
                    // On Unix-like systems, use uname
                    $output = shell_exec('uname -m');
                    if (in_array(trim($output), ['x86_64', 'amd64'])) {
                        return 'x64';
                    } elseif (in_array(trim($output), ['aarch64', 'arm64'])) {
                        return 'arm64';
                    } elseif (in_array(trim($output), ['i386', 'i686'])) {
                        return 'x86';
                    }
                }
                return 'unknown';
        }
    }

    /**
     * Get the download URL for the specified OS and architecture
     * 
     * @param string $os
     * @param string $arch
     * @return string|null
     */
    private function getDownloadUrl(string $os, string $arch): ?string
    {
        // Map OS names to download keys
        $osKey = match ($os) {
            CrossPlatform::OS_WINDOWS => 'windows',
            CrossPlatform::OS_MACOS => 'macos',
            CrossPlatform::OS_LINUX => 'linux',
            default => null,
        };

        if ($osKey === null || !isset(self::DOWNLOAD_URLS[$osKey])) {
            return null;
        }

        if (!isset(self::DOWNLOAD_URLS[$osKey][$arch])) {
            return null;
        }

        return self::DOWNLOAD_URLS[$osKey][$arch];
    }

    /**
     * Download a file from URL to target path
     * 
     * @param string $url
     * @param string $targetPath
     * @return bool
     */
    private function downloadFile(string $url, string $targetPath): bool
    {
        // Clean up any existing partial file
        if (file_exists($targetPath)) {
            unlink($targetPath);
        }
        
        // Try up to 3 times
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            if ($attempt > 1) {
                echo "Retry attempt $attempt/3...\n";
                // Wait a bit before retrying
                sleep(1);
            }
            
            try {
                // Initialize cURL
                $ch = curl_init();
                
                // Set cURL options
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minute timeout
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // 30 second connection timeout
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_USERAGENT, 'OHA GUI Tool Downloader/1.0');
                curl_setopt($ch, CURLOPT_ENCODING, ''); // Enable all encodings
                curl_setopt($ch, CURLOPT_MAXREDIRS, 10); // Maximum number of redirects
                
                // Open file for writing
                $fp = fopen($targetPath, 'w');
                if ($fp === false) {
                    fwrite(STDERR, "Error: Failed to open file for writing: $targetPath\n");
                    curl_close($ch);
                    continue; // Try again
                }
                
                // Set cURL to write directly to file
                curl_setopt($ch, CURLOPT_FILE, $fp);
                
                // Execute cURL request
                $result = curl_exec($ch);
                
                // Get HTTP status code and error info
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                $curlErrno = curl_errno($ch);
                
                // Close file and cURL
                fclose($fp);
                curl_close($ch);
                
                // Check result
                if ($result === false) {
                    fwrite(STDERR, "Error: Failed to download file: $curlError (errno: $curlErrno)\n");
                    // Clean up partial file
                    if (file_exists($targetPath)) {
                        unlink($targetPath);
                    }
                    continue; // Try again
                }
                
                // Check HTTP status code
                if ($httpCode !== 200) {
                    fwrite(STDERR, "Error: HTTP $httpCode when downloading $url\n");
                    // Clean up partial file
                    if (file_exists($targetPath)) {
                        unlink($targetPath);
                    }
                    continue; // Try again
                }
                
                return true; // Success
            } catch (\Exception $e) {
                fwrite(STDERR, "Error: Failed to download file: " . $e->getMessage() . "\n");
                // Clean up partial file
                if (file_exists($targetPath)) {
                    unlink($targetPath);
                }
                continue; // Try again
            }
        }
        
        // All attempts failed
        fwrite(STDERR, "Error: Failed to download file after 3 attempts\n");
        return false;
    }
}