<?php

namespace App\Controller;

use App\Domain\Queue\WorkerManagerInterface;
use App\Domain\Realtime\WorkerStatusUpdatePublisherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class WorkerController extends AbstractController
{
    public function __construct(private WorkerManagerInterface $workerManager) {}

    #[Route('/worker/start', methods: ['POST'])]
    public function start(): JsonResponse
    {
        $this->workerManager->start();
        return $this->json(['message' => 'Worker started successfully']);
    }

    #[Route('/worker/stop', methods: ['POST'])]
    public function stop(): JsonResponse
    {
        $this->workerManager->stop();
        return $this->json(['message' => 'Worker stopped successfully']);
    }

    #[Route('/worker/restart', methods: ['POST'])]
    public function restart(): JsonResponse
    {
        $this->workerManager->restart();
        return $this->json(['message' => 'Worker restarted successfully']);
    }

    #[Route('/worker/status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return $this->json(['status' => $this->workerManager->status()]);
    }
} 