<?php

namespace App\Lib\IkasSync;

class SyncStatus
{
    private string $statusFile;

    private string $stopFile;

    private string $indexFile;

    public function __construct()
    {
        $base = SyncStorage::basePath();
        $this->statusFile = $base.'/app_status.txt';
        $this->stopFile = $base.'/stop.txt';
        $this->indexFile = $base.'/index.txt';
    }

    public function assertNotRunning(): void
    {
        if (config('ikas.app_mode') === 'DEV') {
            return;
        }

        if (! file_exists($this->statusFile)) {
            file_put_contents($this->statusFile, 'done');
        }

        $status = file_get_contents($this->statusFile);

        if ($status === 'running' && (time() - filemtime($this->statusFile)) > 1000) {
            unlink($this->statusFile);

            return;
        }

        if ($status === 'running') {
            echo "App is already running".PHP_EOL;
            exit();
        }

        file_put_contents($this->statusFile, 'running');
    }

    public function update(string $newStatus = 'running'): void
    {
        if (! file_exists($this->stopFile)) {
            file_put_contents($this->stopFile, '');
        }

        if (file_get_contents($this->stopFile) === 'stop') {
            file_put_contents($this->statusFile, $newStatus);
            unlink($this->stopFile);
            exit();
        }

        file_put_contents($this->statusFile, $newStatus);
    }

    public function setProgress(int $current, int $total): void
    {
        file_put_contents($this->indexFile, $current.'/'.$total);
    }

    public function writeLastUpdate(int $start, int $end): void
    {
        file_put_contents(public_path('last_update.txt'), $start.'-'.$end);
    }
}
