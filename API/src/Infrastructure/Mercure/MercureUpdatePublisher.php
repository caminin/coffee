<?php

namespace App\Infrastructure\Mercure;

use App\Domain\Entity\CommandeCafe;
use App\Domain\Realtime\CommandeUpdatePublisherInterface;
use App\Domain\Realtime\WorkerStatusUpdatePublisherInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Serializer\SerializerInterface;

class MercureUpdatePublisher implements CommandeUpdatePublisherInterface, WorkerStatusUpdatePublisherInterface
{
    private HubInterface $hub;
    private SerializerInterface $serializer;

    public function __construct(HubInterface $hub, SerializerInterface $serializer)
    {
        $this->hub = $hub;
        $this->serializer = $serializer;
    }

    public function publishCommandeUpdate(CommandeCafe $commande, array $topics = [], ?string $eventName = null): void
    {
        $jsonContent = $this->serializer->serialize($commande, 'json', ['groups' => 'commande:read']);

        $defaultTopic = sprintf('/commandes/%s', $commande->getId());
        $allTopics = array_merge([$defaultTopic, '/commandes'], $topics);

        $update = new Update(
            array_unique($allTopics),
            $jsonContent,
            false, // Le message est privé par défaut, nécessite un JWT pour s'abonner si le hub est configuré ainsi
            null, // id
            $eventName ?? self::EVENT_COMMANDE_MISE_A_JOUR_GENERIQUE // type (event name for SSE)
            // retry
        );

        $this->hub->publish($update);
    }

    public function publishCommandeDeleted(string $commandeId, array $topics = [], ?string $eventName = null): void
    {
        $data = ['id' => $commandeId, 'status' => 'deleted'];
        $jsonContent = $this->serializer->serialize($data, 'json');

        $defaultTopic = sprintf('/commandes/%s', $commandeId);
        $allTopics = array_merge([$defaultTopic, '/commandes'], $topics);

        $update = new Update(
            array_unique($allTopics),
            $jsonContent,
            false,
            null,
            $eventName ?? self::EVENT_COMMANDE_SUPPRIMEE
        );

        $this->hub->publish($update);
    }

    public function publishWorkerStatus(string $status, array $context = [], ?string $eventName = null): void
    {
        $data = array_merge($context, ['status' => $status, 'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339)]);
        $jsonContent = $this->serializer->serialize($data, 'json');

        $genericTopic = '/worker/status'; // Le topic que le frontend écoute

        $update = new Update(
            [$genericTopic], // Publier sur les deux topics
            $jsonContent,
            false,
            null,
            $eventName ?? 'worker_status_updated'
        );

        $this->hub->publish($update);
    }
} 