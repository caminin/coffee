<?php

namespace App\Domain\Repository;

use App\Domain\Entity\CommandeCafe;

interface CommandeCafeRepositoryInterface
{
    public function save(CommandeCafe $commande): void;

    public function findById(int $id): ?CommandeCafe;

    public function deleteById(int $commandeId): bool;

    /**
     * Trouve les N dernières commandes, triées par date de création décroissante.
     *
     * @param int $limit Le nombre maximum de commandes à retourner.
     * @return CommandeCafe[] La liste des commandes.
     */
    public function findRecentByDateDesc(int $limit): array;
} 