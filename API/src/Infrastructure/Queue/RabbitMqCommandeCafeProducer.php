<?php

namespace App\Infrastructure\Queue;

use App\Domain\Entity\CommandeCafe;
use App\Domain\Queue\CommandeCafeQueueProducerInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMqCommandeCafeProducer implements CommandeCafeQueueProducerInterface
{
    private $connection;
    private $channel;
    private $queueName;

    public function __construct(string $host, int $port, string $user, string $password, string $queueName)
    {
        $this->connection = new AMQPStreamConnection($host, $port, $user, $password);
        $this->channel = $this->connection->channel();
        $this->queueName = $queueName;
        $this->channel->queue_declare($queueName, false, true, false, false);
    }

    public function publish(CommandeCafe $commande): void
    {
        $payload = [
            'id' => $commande->getId(),
            'type' => $commande->getType(),
            'intensite' => $commande->getIntensite(),
            'taille' => $commande->getTaille(),
            'statut' => $commande->getStatut(),
            'dateCreation' => $commande->getDateCreationString(),
            'dateDebutPreparation' => $commande->getDateDebutPreparationString(),
            'dateFinPreparation' => $commande->getDateFinPreparationString(),
        ];
        $msg = new AMQPMessage(json_encode($payload), ['content_type' => 'application/json']);
        $this->channel->basic_publish($msg, '', $this->queueName);
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
} 