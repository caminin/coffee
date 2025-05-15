<?php

namespace App\Domain\Realtime;

interface WorkerStatusUpdatePublisherInterface
{
    public const STATUS_STARTED = 'started';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_STOPPED = 'stopped';
    public const STATUS_UNKNOWN = 'unknown';
    public const STATUS_ERROR = 'error';

    /**
     * Publie une mise à jour de l'état d'un worker.
     *
     * @param string $status Le nouveau statut du worker (utiliser les constantes de l'interface).
     * @param array<string, mixed> $context Données contextuelles supplémentaires (ex: ID de la commande en cours).
     * @param string|null $eventName Nom de l'événement pour le SSE.
     */
    public function publishWorkerStatus(string $status, array $context = [], ?string $eventName = null): void;
} 