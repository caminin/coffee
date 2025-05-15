<?php

namespace App\Domain\Queue;

interface WorkerManagerInterface
{
    public function start(): void;
    public function stop(): void;
    public function restart(): void;
    public function status(): string;
} 