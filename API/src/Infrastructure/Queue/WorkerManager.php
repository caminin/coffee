<?php

namespace App\Infrastructure\Queue;

use App\Domain\Queue\WorkerManagerInterface;
use App\Domain\Realtime\WorkerStatusUpdatePublisherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class WorkerManager implements WorkerManagerInterface
{
    private string $workerName = 'coffee-worker'; // Nom du programme dans Supervisor

    public function __construct(
        private readonly WorkerStatusUpdatePublisherInterface $statusUpdatePublisher
    ) {
    }

    private function runSupervisorctl(array $command): bool
    {
        $process = new Process(array_merge(['supervisorctl'], $command));
        try {
            $process->mustRun();
            return true;
        } catch (ProcessFailedException $exception) {
            return false;
        }
    }

    public function start(): void
    {
        if ($this->runSupervisorctl(['start', $this->workerName])) {
            $this->statusUpdatePublisher->publishWorkerStatus($this->status(), ['workerId' => $this->workerName]);
        } else {
            $this->statusUpdatePublisher->publishWorkerStatus(WorkerStatusUpdatePublisherInterface::STATUS_ERROR, ['workerId' => $this->workerName, 'error' => "Impossible de démarrer le worker."]);
            throw new \RuntimeException("Impossible de démarrer le worker '{$this->workerName}'. Consultez les logs.");
        }
    }

    public function stop(): void
    {
        if ($this->runSupervisorctl(['stop', $this->workerName])) {
            $this->statusUpdatePublisher->publishWorkerStatus($this->status(), ['workerId' => $this->workerName]);
        } else {
            $this->statusUpdatePublisher->publishWorkerStatus(WorkerStatusUpdatePublisherInterface::STATUS_ERROR, ['workerId' => $this->workerName, 'error' => "Impossible d'arrêter le worker."]);
            throw new \RuntimeException("Impossible d'arrêter le worker '{$this->workerName}'. Consultez les logs.");
        }
    }

    public function restart(): void
    {
        if ($this->runSupervisorctl(['restart', $this->workerName])) {
            $this->statusUpdatePublisher->publishWorkerStatus($this->status(), ['workerId' => $this->workerName]);
        } else {
            $this->statusUpdatePublisher->publishWorkerStatus(WorkerStatusUpdatePublisherInterface::STATUS_ERROR, ['workerId' => $this->workerName, 'error' => "Impossible de redémarrer le worker."]);
            throw new \RuntimeException("Impossible de redémarrer le worker '{$this->workerName}'. Consultez les logs.");
        }
    }

    public function status(): string
    {
        $process = new Process(['supervisorctl', 'status', $this->workerName]);
        try {
            $process->run();
            $output = $process->getOutput();

            if (!$process->isSuccessful()) {
                return WorkerStatusUpdatePublisherInterface::STATUS_STOPPED;
            }

            if (str_contains($output, 'RUNNING')) {
                return WorkerStatusUpdatePublisherInterface::STATUS_STARTED;
            } elseif (str_contains($output, 'STOPPED') || str_contains($output, 'EXITED') || str_contains($output, 'FATAL')) {
                return WorkerStatusUpdatePublisherInterface::STATUS_STOPPED;
            }
            return WorkerStatusUpdatePublisherInterface::STATUS_UNKNOWN;
        } catch (ProcessFailedException $exception) {
            return WorkerStatusUpdatePublisherInterface::STATUS_ERROR;
        }
    }
} 