<?php

namespace App\Domain\Realtime;

use App\Domain\Entity\CommandeCafe;

interface CommandeUpdatePublisherInterface
{
    public const EVENT_COMMANDE_CREEE = 'commande_creee';
    public const EVENT_COMMANDE_EN_COURS = 'commande_en_cours';
    public const EVENT_COMMANDE_TERMINEE = 'commande_terminee';
    public const EVENT_COMMANDE_ANNULEE = 'commande_annulee';
    public const EVENT_COMMANDE_SUPPRIMEE = 'commande_deleted'; // Pour publishCommandeDeleted
    public const EVENT_COMMANDE_MISE_A_JOUR_GENERIQUE = 'commande_updated'; // Fallback

    /**
     * Publie une mise à jour pour une commande de café.
     *
     * @param CommandeCafe $commande L'entité CommandeCafe mise à jour.
     * @param string[] $topics Optionnel, liste de topics supplémentaires sur lesquels publier.
     *                         Par défaut, un topic basé sur l'ID de la commande sera utilisé.
     * @param string|null $eventName Nom de l'événement pour le SSE (Server-Sent Event).
     */
    public function publishCommandeUpdate(CommandeCafe $commande, array $topics = [], ?string $eventName = null): void;

    /**
     * Publie un message indiquant qu'une commande a été supprimée.
     *
     * @param string $commandeId L'ID de la commande supprimée.
     * @param string[] $topics Optionnel, liste de topics supplémentaires.
     * @param string|null $eventName Nom de l'événement pour le SSE.
     */
    public function publishCommandeDeleted(string $commandeId, array $topics = [], ?string $eventName = null): void;
} 