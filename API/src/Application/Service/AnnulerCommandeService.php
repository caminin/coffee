<?php

namespace App\Application\Service;

use App\Domain\Entity\CommandeCafe;
use App\Domain\Realtime\CommandeUpdatePublisherInterface;
use App\Domain\Repository\CommandeCafeRepositoryInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

class AnnulerCommandeService
{
    private CommandeCafeRepositoryInterface $commandeRepository;
    private CommandeUpdatePublisherInterface $commandeUpdatePublisher;
    private CacheItemPoolInterface $redisCachePool;
    private LoggerInterface $logger;

    public function __construct(
        CommandeCafeRepositoryInterface $commandeRepository,
        CommandeUpdatePublisherInterface $commandeUpdatePublisher,
        CacheItemPoolInterface $redisCachePool,
        LoggerInterface $logger
    ) {
        $this->commandeRepository = $commandeRepository;
        $this->commandeUpdatePublisher = $commandeUpdatePublisher;
        $this->redisCachePool = $redisCachePool;
        $this->logger = $logger;
    }

    /**
     * Annule une commande de café.
     *
     * @param int $commandeId L'ID de la commande à annuler.
     * @return CommandeCafe L'entité CommandeCafe mise à jour.
     * @throws \RuntimeException Si la commande n'est pas trouvée ou ne peut pas être annulée.
     */
    public function annuler(int $commandeId): CommandeCafe
    {
        $commande = $this->commandeRepository->findById($commandeId);

        if (!$commande) {
            $this->logger->error("Tentative d'annulation pour une commande inexistante.", ['commandeId' => $commandeId]);
            throw new \RuntimeException("Commande avec ID {$commandeId} non trouvée pour annulation.");
        }

        // Vérifier si la commande peut être annulée (par exemple, pas déjà terminée ou annulée)
        if (!in_array($commande->getStatut(), [CommandeCafe::STATUT_EN_ATTENTE, CommandeCafe::STATUT_EN_COURS])) {
            $this->logger->warning("Tentative d'annuler une commande qui n'est plus annulable.", [
                'commandeId' => $commandeId,
                'statut' => $commande->getStatut()
            ]);
            // Retourner la commande sans la modifier ou lever une exception selon la logique métier souhaitée
            return $commande; 
        }

        $commande->setStatut(CommandeCafe::STATUT_ANNULEE); 
        $this->commandeRepository->save($commande);

        $this->commandeUpdatePublisher->publishCommandeUpdate(
            $commande, 
            ['motif' => 'Annulation demandée par l\'utilisateur'], // Contexte optionnel
            CommandeUpdatePublisherInterface::EVENT_COMMANDE_ANNULEE // Utilisation de la constante de l'interface
        );

        // Écrire le flag d'annulation dans Redis
        $cacheKey = 'annulation_commande_' . $commandeId;
        $cacheItem = $this->redisCachePool->getItem($cacheKey);
        $cacheItem->set(true);
        $cacheItem->expiresAfter(300); 
        $this->redisCachePool->save($cacheItem);

        $this->logger->info("Commande {$commandeId} annulée et flag positionné dans Redis.", [
            'commandeId' => $commandeId,
            'redisKey' => $cacheKey
        ]);

        return $commande;
    }
} 