<?php
/**
 * Panelion - Logger
 */

namespace Panelion\Core;

class Logger
{
    private string $logPath;

    public function __construct(string $logPath)
    {
        $this->logPath = $logPath;
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0750, true);
        }
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('CRITICAL', $message, $context);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $date = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logLine = "[{$date}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

        $logFile = $this->logPath . '/panelion-' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    public function getLogFiles(): array
    {
        $files = glob($this->logPath . '/panelion-*.log');
        rsort($files);
        return $files;
    }

    public function readLog(string $filename, int $lines = 100): array
    {
        $filepath = $this->logPath . '/' . basename($filename);
        if (!file_exists($filepath)) {
            return [];
        }

        $file = new \SplFileObject($filepath, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $start = max(0, $totalLines - $lines);
        $result = [];
        $file->seek($start);

        while (!$file->eof()) {
            $line = trim($file->fgets());
            if (!empty($line)) {
                $result[] = $line;
            }
        }

        return $result;
    }
}
