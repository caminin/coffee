<?php

namespace App\Domain\Queue;

interface MessageConsumerInterface
{
    /**
     * Bloque jusqu'à ce qu'un message soit reçu ou qu'un timeout survienne.
     * Retourne le message ou null en cas de timeout ou d'erreur gérée.
     */
    public function consume(callable $callback, int $timeout = 0): void;

    /**
     * Acquitte un message.
     */
    public function ack(mixed $messageDeliveryTag): void;

    /**
     * Rejette un message, avec option de le remettre en file.
     */
    public function nack(mixed $messageDeliveryTag, bool $requeue = false): void;

    /**
     * Demande au consommateur d'arrêter de consommer des messages.
     * Utile pour un arrêt propre lorsque des signaux sont reçus.
     */
    public function stopConsuming(): void;

    /**
     * Ferme la connexion à la file de messages.
     */
    public function close(): void;
} 