<?php

namespace App\Controller;

use App\Application\Service\AnnulerCommandeService;
use App\Domain\Entity\CommandeCafe;
use App\Domain\Queue\CommandeCafeQueueProducerInterface;
use App\Domain\Realtime\CommandeUpdatePublisherInterface;
use App\Domain\Repository\CommandeCafeRepositoryInterface;
use Overblog\GraphQLBundle\Definition\Argument;
use Overblog\GraphQLBundle\Definition\Resolver\MutationInterface;

class CommandeCafeMutationResolver implements MutationInterface
{
    private CommandeCafeQueueProducerInterface $queueProducer;
    private CommandeCafeRepositoryInterface $commandeRepository;
    private CommandeUpdatePublisherInterface $commandeUpdatePublisher;
    private AnnulerCommandeService $annulerCommandeService;

    public function __construct(
        CommandeCafeQueueProducerInterface $queueProducer,
        CommandeCafeRepositoryInterface $commandeRepository,
        CommandeUpdatePublisherInterface $commandeUpdatePublisher,
        AnnulerCommandeService $annulerCommandeService
    ) {
        $this->queueProducer = $queueProducer;
        $this->commandeRepository = $commandeRepository;
        $this->commandeUpdatePublisher = $commandeUpdatePublisher;
        $this->annulerCommandeService = $annulerCommandeService;
    }

    public function create(Argument $args): CommandeCafe
    {
        $type = $args['type'];
        $intensite = $args['intensite'];
        $taille = $args['taille'];
        $statut = CommandeCafe::STATUT_EN_ATTENTE;
        $dateCreation = new \DateTimeImmutable();

        $commande = new CommandeCafe(
            $type,
            $intensite,
            $taille,
            $statut,
            $dateCreation
        );

        // Persistance via le repository du domaine
        $this->commandeRepository->save($commande);

        // Publication de la création via Mercure
        $this->commandeUpdatePublisher->publishCommandeUpdate($commande, [], CommandeUpdatePublisherInterface::EVENT_COMMANDE_CREEE);

        // Envoi dans la queue via le producer du domaine
        $this->queueProducer->publish($commande);

        return $commande;
    }

    /**
     * Résout la mutation pour supprimer une commande de café.
     *
     * @param string $id L'ID de la commande à supprimer (reçu directement grâce à la conf GraphQL).
     * @return array{id: ?int, success: bool, message: string}
     */
    public function deleteCommande(string $id): array
    {
        // L'argument ID de GraphQL est une chaîne, mais nos services/repositories attendent un int.
        $commandeId = (int)$id;

        $deleted = $this->commandeRepository->deleteById($commandeId);

        if ($deleted) {

            // Notifier via Mercure
            $this->commandeUpdatePublisher->publishCommandeDeleted((string)$commandeId, [], CommandeUpdatePublisherInterface::EVENT_COMMANDE_SUPPRIMEE);
            
            return ['id' => $commandeId, 'success' => true, 'message' => 'Commande supprimée avec succès.'];
        } else {
            return ['id' => $commandeId, 'success' => false, 'message' => 'Commande non trouvée lors de la suppression.'];
        }
    }

    /**
     * Résout la mutation pour annuler une commande de café.
     *
     * @param string $id L'ID de la commande à annuler.
     * @return array{id: ?int, success: bool, message: string, statut: ?string}
     */
    public function annulerCommande(string $id): array
    {
        $commandeId = (int)$id;
        try {
            $commandeAnnulee = $this->annulerCommandeService->annuler($commandeId);

            if ($commandeAnnulee->getStatut() === CommandeCafe::STATUT_ANNULEE) {
                return [
                    'id' => $commandeAnnulee->getId(),
                    'success' => true,
                    'message' => 'Commande annulée avec succès.',
                    'statut' => $commandeAnnulee->getStatut(),
                ];
            } else {
                return [
                    'id' => $commandeAnnulee->getId(),
                    'success' => false,
                    'message' => 'La commande n\'a pas pu être annulée (statut actuel: ' . $commandeAnnulee->getStatut() . ').',
                    'statut' => $commandeAnnulee->getStatut(),
                ];
            }
        } catch (\RuntimeException $e) {
            $messageErreur = "Erreur lors de l'annulation: " . $e->getMessage();
            if (str_contains(strtolower($e->getMessage()), 'non trouvée')) {
                 $messageErreur = "Commande ID {$commandeId} non trouvée pour annulation.";
            }
            return ['id' => $commandeId, 'success' => false, 'message' => $messageErreur, 'statut' => null];
        } catch (\Throwable $e) {
            return ['id' => $commandeId, 'success' => false, 'message' => 'Une erreur serveur inattendue est survenue lors de l\'annulation.', 'statut' => null];
        }
    }
}