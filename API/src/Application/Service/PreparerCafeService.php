<?php

namespace App\Application\Service;

use App\Domain\Entity\CommandeCafe;
use App\Domain\Realtime\CommandeUpdatePublisherInterface;
use App\Domain\Realtime\WorkerStatusUpdatePublisherInterface;
use App\Domain\Repository\CommandeCafeRepositoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

class PreparerCafeService
{
    private LoggerInterface $logger;
    private CommandeUpdatePublisherInterface $commandeUpdatePublisher;
    private CommandeCafeRepositoryInterface $commandeRepository;
    private WorkerStatusUpdatePublisherInterface $workerStatusUpdatePublisher;
    private CacheItemPoolInterface $redisCachePool;

    // Temps de base en secondes
    private const BASE_TIME_MOUTURE = 1.5;
    private const BASE_TIME_INFUSION = 2.0;
    private const BASE_TIME_DISTRIBUTION = 1.5;
    private const MAX_SLEEP_INTERVAL = 1.0; // Durée maximale pour un sleep individuel

    // Ajustements de temps (secondes)
    // Tailles (appliqué à infusion et distribution)
    private const AJUSTEMENT_TAILLE_PETIT = -0.5;
    private const AJUSTEMENT_TAILLE_GRAND = 1.0;
    // Types (ajustements spécifiques par étape)
    private const AJUSTEMENT_TYPE_LUNGO_INFUSION = 1.0;
    private const AJUSTEMENT_TYPE_LUNGO_DISTRIBUTION = 0.5;
    private const AJUSTEMENT_TYPE_CAPPUCCINO_DISTRIBUTION = 1.0; // Pour le lait, simplifié
    private const AJUSTEMENT_TYPE_LATTE_DISTRIBUTION = 1.5;    // Pour plus de lait, simplifié

    public function __construct(
        LoggerInterface $logger,
        CommandeUpdatePublisherInterface $commandeUpdatePublisher,
        WorkerStatusUpdatePublisherInterface $workerStatusUpdatePublisher,
        CommandeCafeRepositoryInterface $commandeRepository,
        CacheItemPoolInterface $redisCachePool
    ) {
        $this->logger = $logger;
        $this->commandeUpdatePublisher = $commandeUpdatePublisher;
        $this->commandeRepository = $commandeRepository;
        $this->workerStatusUpdatePublisher = $workerStatusUpdatePublisher;
        $this->redisCachePool = $redisCachePool;
    }

    private function handleInterruptionParAnnulation(CommandeCafe $commandeOriginale): CommandeCafe
    {
        $commandeId = $commandeOriginale->getId();
        $this->logger->info("Préparation interrompue par annulation (signal Redis) pour commande {$commandeId}.");
        
        // Recharger la commande pour s'assurer d'avoir le statut le plus récent (mis à jour par AnnulerCommandeService)
        $commandeActuelle = $this->commandeRepository->findById($commandeId);

        if (!$commandeActuelle) {
            // Cas très improbable si la commande originale existait, mais gérons-le.
            $this->logger->error("Impossible de recharger la commande {$commandeId} après détection d'annulation.");
            return $commandeOriginale; // Retourner l'originale, qui a potentiellement un ancien statut
        }

        if ($commandeActuelle->getStatut() === CommandeCafe::STATUT_ANNULEE) {
            $this->commandeUpdatePublisher->publishCommandeUpdate(
                $commandeActuelle, 
                [], 
                CommandeUpdatePublisherInterface::EVENT_COMMANDE_TERMINEE
            );
            $this->logger->info("Événement EVENT_COMMANDE_TERMINEE publié pour la commande annulée {$commandeId}.");
        } else {
            $this->logger->warning("Commande {$commandeId} interrompue mais son statut actuel en BDD n'est pas ANNULEE (statut: {$commandeActuelle->getStatut()}). L'événement TERMINEE spécifique à l'interruption par annulation ne sera pas publié.");
        }
        return $commandeActuelle;
    }

    /**
     * Simule un travail en découpant le sleep et en vérifiant l'annulation.
     * @return bool True si annulé, False sinon.
     */
    private function simulateWork(float $totalDuration, string $redisCancelKey, string $stepNameForLog): bool
    {
        $remainingDuration = max(0, $totalDuration);
        $this->logger->debug("Début étape '{$stepNameForLog}' pour une durée calculée de {$totalDuration}s.");

        while ($remainingDuration > 0) {
            $cancelFlagItem = $this->redisCachePool->getItem($redisCancelKey);
            if ($cancelFlagItem->isHit() && $cancelFlagItem->get() === true) {
                $this->logger->info("Annulation détectée pendant l'étape '{$stepNameForLog}'.");
                return true; // Annulé
            }

            $sleepTime = min(self::MAX_SLEEP_INTERVAL, $remainingDuration);
            usleep((int)($sleepTime * 1000000)); // usleep prend des microsecondes
            $remainingDuration -= $sleepTime;
            $this->logger->debug("Étape '{$stepNameForLog}': sleep {$sleepTime}s, restant {$remainingDuration}s");
        }
        return false; // Non annulé, travail complété
    }

    /**
     * Simule la préparation d'un café et met à jour l'entité CommandeCafe.
     *
     * @param int $commandeId L'ID de la commande à préparer.
     * @param string $type
     * @param string $size
     * @param int $intensity
     * @return CommandeCafe L'entité CommandeCafe mise à jour et persistée.
     * @throws \Exception Si la commande n'est pas trouvée.
     */
    public function preparer(int $commandeId, string $type, string $size, int $intensity): CommandeCafe
    {
        // Utiliser les arguments $type, $size, $intensity pour les calculs
        $typeNormalise = strtoupper($type);
        $tailleNormalisee = strtoupper($size);

        $commande = $this->commandeRepository->findById($commandeId);

        if (!$commande) {
            $this->logger->error("Tentative de préparation pour une commande inexistante.", ['commandeId' => $commandeId]);
            $this->workerStatusUpdatePublisher->publishWorkerStatus(
                WorkerStatusUpdatePublisherInterface::STATUS_ERROR, 
                ['message' => "Commande avec ID {$commandeId} non trouvée pour la préparation.", 'commandeId' => $commandeId]
            );
            throw new \RuntimeException("Commande avec ID {$commandeId} non trouvée pour la préparation.");
        }

        $redisCancelKey = 'annulation_commande_' . $commandeId;

        $cancelFlagItem = $this->redisCachePool->getItem($redisCancelKey);
        if ($cancelFlagItem->isHit() && $cancelFlagItem->get() === true) {
            $this->logger->info("Préparation non démarrée (signal Redis) pour commande {$commandeId} car déjà annulée.");
            return $this->commandeRepository->findById($commandeId) ?? $commande;
        }

        if ($commande->getStatut() !== CommandeCafe::STATUT_EN_ATTENTE) {
            $this->logger->warning("Tentative de préparer une commande qui n'est pas en attente.", ['commandeId' => $commandeId, 'statut' => $commande->getStatut()]);
            return $commande;
        }

        $commande->setStatut(CommandeCafe::STATUT_EN_COURS);
        $commande->setDateDebutPreparation(new \DateTimeImmutable());
        $this->commandeRepository->save($commande);
        $this->commandeUpdatePublisher->publishCommandeUpdate($commande, [], CommandeUpdatePublisherInterface::EVENT_COMMANDE_EN_COURS);
        $this->logger->info("Début de la préparation du café (statut EN_COURS)", [
            'commandeId' => $commande->getId(),
            'type' => $typeNormalise, // Utiliser la valeur normalisée
            'size' => $tailleNormalisee, // Utiliser la valeur normalisée
            'intensity' => $intensity
        ]);
        
        // --- Calcul des temps ---
        // Mouture (pour l'instant, pas d'ajustement basé sur type/taille/intensité, mais pourrait être ajouté)
        $tempsMouture = self::BASE_TIME_MOUTURE;
        // L'intensité pourrait augmenter légèrement le temps de mouture
        if ($intensity > 7) $tempsMouture += 0.5;
        elseif ($intensity < 4) $tempsMouture -= 0.2;
        $tempsMouture = max(0.5, $tempsMouture); // Temps minimal

        // Infusion
        $tempsInfusion = self::BASE_TIME_INFUSION;
        if ($tailleNormalisee === CommandeCafe::TAILLE_PETIT) $tempsInfusion += self::AJUSTEMENT_TAILLE_PETIT;
        elseif ($tailleNormalisee === CommandeCafe::TAILLE_GRAND) $tempsInfusion += self::AJUSTEMENT_TAILLE_GRAND;
        if ($typeNormalise === CommandeCafe::TYPE_LUNGO) $tempsInfusion += self::AJUSTEMENT_TYPE_LUNGO_INFUSION;
        $tempsInfusion = max(0.5, $tempsInfusion); // Temps minimal

        // Distribution
        $tempsDistribution = self::BASE_TIME_DISTRIBUTION;
        if ($tailleNormalisee === CommandeCafe::TAILLE_PETIT) $tempsDistribution += self::AJUSTEMENT_TAILLE_PETIT;
        elseif ($tailleNormalisee === CommandeCafe::TAILLE_GRAND) $tempsDistribution += self::AJUSTEMENT_TAILLE_GRAND;
        
        switch ($typeNormalise) {
            case CommandeCafe::TYPE_LUNGO:
                $tempsDistribution += self::AJUSTEMENT_TYPE_LUNGO_DISTRIBUTION;
                break;
            case CommandeCafe::TYPE_CAPPUCCINO:
                $tempsDistribution += self::AJUSTEMENT_TYPE_CAPPUCCINO_DISTRIBUTION;
                break;
            case CommandeCafe::TYPE_LATTE:
                $tempsDistribution += self::AJUSTEMENT_TYPE_LATTE_DISTRIBUTION;
                break;
        }
        $tempsDistribution = max(0.5, $tempsDistribution); // Temps minimal

        // --- Exécution des étapes ---
        $this->logger->info("Début mouture (calculé: {$tempsMouture}s) pour commande {$commande->getId()}.");
        if ($this->simulateWork($tempsMouture, $redisCancelKey, "mouture")) {
            return $this->handleInterruptionParAnnulation($commande);
        }
        $this->logger->info("Mouture terminée pour le café {$typeNormalise} (Commande: {$commande->getId()}).");
        
        $this->logger->info("Début infusion (calculé: {$tempsInfusion}s) pour commande {$commande->getId()}.");
        if ($this->simulateWork($tempsInfusion, $redisCancelKey, "infusion")) {
            return $this->handleInterruptionParAnnulation($commande);
        }
        $this->logger->info("Infusion terminée pour le café {$typeNormalise} (Commande: {$commande->getId()}).");
        
        $this->logger->info("Début distribution (calculé: {$tempsDistribution}s) pour commande {$commande->getId()}.");
        if ($this->simulateWork($tempsDistribution, $redisCancelKey, "distribution")) {
            return $this->handleInterruptionParAnnulation($commande);
        }
        $this->logger->info("Distribution terminée pour le café {$typeNormalise} (Commande: {$commande->getId()}).");

        // Vérifier une dernière fois avant de finaliser, au cas où.
        $cancelFlagItem = $this->redisCachePool->getItem($redisCancelKey);
        if ($cancelFlagItem->isHit() && $cancelFlagItem->get() === true) {
             return $this->handleInterruptionParAnnulation($commande);
        }

        $commande->setStatut(CommandeCafe::STATUT_TERMINEE);
        $commande->setDateFinPreparation(new \DateTimeImmutable());
        $this->commandeRepository->save($commande);

        $this->logger->info("Café préparé avec succès", [
            'commandeId' => $commande->getId(),
            'type' => $typeNormalise,
            'size' => $tailleNormalisee,
            'intensity' => $intensity,
            'statut' => $commande->getStatut()
        ]);

        $this->commandeUpdatePublisher->publishCommandeUpdate($commande, [], CommandeUpdatePublisherInterface::EVENT_COMMANDE_TERMINEE);

        return $commande;
    }
} 