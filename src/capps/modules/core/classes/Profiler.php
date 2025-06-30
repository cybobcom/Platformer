<?php

namespace capps\modules\core\classes;

class Profiler {
    private array $checkpoints = [];
    private float $startTime;
    private int $startMemory;

    public function __construct() {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
        $this->checkpoints[] = [
            'label' => 'Start',
            'time_s' => 0.000,
            'memory_mb' => 0
        ];
    }

    public function checkpoint(string $label): void {
        $currentTime = microtime(true) - $this->startTime;
        $currentMemory = (memory_get_usage() - $this->startMemory) / 1024 / 1024;
        $this->checkpoints[] = [
            'label' => $label,
            'time_s' => round($currentTime, 3),
            'memory_mb' => round($currentMemory, 3)
        ];
    }



    public function getTotalTime(): float {
        return round(microtime(true) - $this->startTime, 3);
    }

    public function report(): string {
        $reportData = $this->checkpoints;
        $reportData[] = [
            'label' => 'Gesamtzeit',
            'time_s' => $this->getTotalTime()
        ];
        return json_encode($reportData, JSON_PRETTY_PRINT);
    }

    public function recordExecutionEnd()
    {

    }

    public function getStats()
    {
        $stats = array();
        $stats['execution_time'] = "";
        $stats['memory_usage'] = "";

        return $stats;
    }
}

?>