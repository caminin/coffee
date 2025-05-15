<?php

namespace App\Controller;

use App\Domain\Entity\CommandeCafe;
use App\Domain\Repository\CommandeCafeRepositoryInterface;
use Overblog\GraphQLBundle\Definition\Argument;
use Overblog\GraphQLBundle\Definition\Resolver\QueryInterface;

class CommandeCafeQueryResolver implements QueryInterface
{
    private CommandeCafeRepositoryInterface $commandeRepository;

    public function __construct(CommandeCafeRepositoryInterface $commandeRepository)
    {
        $this->commandeRepository = $commandeRepository;
    }

    /**
     * Résout la requête pour obtenir les N dernières commandes de café.
     *
     * @param Argument $args Les arguments de la requête GraphQL (contient 'limit').
     * @return CommandeCafe[]
     */
    public function getDernieresCommandes(Argument $args): array
    {
        $limit = $args['limit'] ?? 20; // Utilise la valeur par défaut si non fournie

        // Utiliser directement la méthode findRecentByDateDesc de l'interface
        if (method_exists($this->commandeRepository, 'findRecentByDateDesc')) {
            return $this->commandeRepository->findRecentByDateDesc((int)$limit);
        } else {
            throw new \LogicException("La méthode 'findRecentByDateDesc' n'est pas implémentée dans le repository des commandes café, bien qu'elle soit définie dans l'interface.");
        }
    }
} 