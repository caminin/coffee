<?php

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Entity\CommandeCafe as DomainCommandeCafe;
use App\Domain\Repository\CommandeCafeRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\CommandeCafe as DoctrineCommandeCafe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends ServiceEntityRepository<DoctrineCommandeCafe>
 *
 * @method DoctrineCommandeCafe|null find($id, $lockMode = null, $lockVersion = null)
 * @method DoctrineCommandeCafe|null findOneBy(array $criteria, array $orderBy = null)
 * @method DoctrineCommandeCafe[]    findAll()
 * @method DoctrineCommandeCafe[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommandeCafeRepository extends ServiceEntityRepository implements CommandeCafeRepositoryInterface
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, DoctrineCommandeCafe::class);
        $this->entityManager = $entityManager;
    }

    public function save(DomainCommandeCafe $commande): void
    {
        if ($commande->getId() === null) {
            // C'est une nouvelle commande
            $doctrineCommande = new DoctrineCommandeCafe();
        } else {
            // C'est une commande existante, on la charge
            $doctrineCommande = $this->find($commande->getId());
            if (!$doctrineCommande) {
                // Ou gérer l'erreur autrement, par exemple lever une exception CommandeNonTrouvee
                throw new \RuntimeException(sprintf("Impossible de trouver la CommandeCafe Doctrine avec l'ID %d pour la mise à jour.", $commande->getId()));
            }
        }

        // Mapper les propriétés du Domaine vers Doctrine
        $doctrineCommande->setType($commande->getType());
        $doctrineCommande->setIntensite($commande->getIntensite());
        $doctrineCommande->setTaille($commande->getTaille());
        $doctrineCommande->setStatut($commande->getStatut());
        $doctrineCommande->setDateCreation($commande->getDateCreation());
        $doctrineCommande->setDateDebutPreparation($commande->getDateDebutPreparation());
        $doctrineCommande->setDateFinPreparation($commande->getDateFinPreparation());

        $this->entityManager->persist($doctrineCommande);
        $this->entityManager->flush();

        if ($doctrineCommande->getId() === null) {
            throw new \RuntimeException("L\'ID de l\'entité Doctrine CommandeCafe n\'a pas été généré après flush.");
        }

        if ($commande->getId() === null) { // On ne met à jour que si l'ID du domaine n'était pas déjà défini (cas d'une création)
            $commande->setId($doctrineCommande->getId());
        }
    }

    public function findById(int $id): ?DomainCommandeCafe
    {
        $doctrineCommande = $this->find($id);

        if (!$doctrineCommande) { 
            return null;
        }
        $dateCreation = $doctrineCommande->getDateCreation();
        if ($dateCreation === null) {
             throw new \LogicException("La date de création ne peut être nulle pour une entité Doctrine CommandeCafe lors du mapping vers le Domaine.");
        }
        
        // Mapper Doctrine vers Domaine
        return new DomainCommandeCafe(
            $doctrineCommande->getType(),
            (int)$doctrineCommande->getIntensite(),
            $doctrineCommande->getTaille(),
            $doctrineCommande->getStatut(),
            $dateCreation,
            $doctrineCommande->getId(),
            $doctrineCommande->getDateDebutPreparation(),
            $doctrineCommande->getDateFinPreparation()
        );
    }

    public function remove(DomainCommandeCafe $commandeCafe): void
    {
        $entity = $this->fromDomain($commandeCafe);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    /** @return DomainCommandeCafe[] */
    public function findAll(): array
    {
        return array_map(fn($entity) => $this->toDomain($entity), parent::findAll());
    }

    private function toDomain(DoctrineCommandeCafe $entity): DomainCommandeCafe
    {
        // S'assurer que les dates optionnelles sont bien null si non définies dans l'entité Doctrine
        $dateCreation = $entity->getDateCreation();
        if ($dateCreation === null) {
            throw new \LogicException("La date de création ne peut être nulle pour une entité Doctrine CommandeCafe lors du mapping vers le Domaine.");
        }

        return new DomainCommandeCafe(
            $entity->getType(),
            (int)$entity->getIntensite(),
            $entity->getTaille(),
            $entity->getStatut(),
            $dateCreation,
            $entity->getId(),
            $entity->getDateDebutPreparation(),
            $entity->getDateFinPreparation(),
            null
        );
    }

    private function fromDomain(DomainCommandeCafe $domain): DoctrineCommandeCafe
    {
        $entity = $domain->getId() ? $this->find($domain->getId()) : new DoctrineCommandeCafe();
        // Si $entity est null après un find (cas où l'ID domaine existe mais pas en BDD), il faut le créer
        if ($entity === null) { 
            $entity = new DoctrineCommandeCafe();
        }

        $entity->setType($domain->getType());
        $entity->setIntensite($domain->getIntensite());
        $entity->setTaille($domain->getTaille());
        $entity->setStatut($domain->getStatut());
        $entity->setDateCreation($domain->getDateCreation());
        $entity->setDateDebutPreparation($domain->getDateDebutPreparation());
        $entity->setDateFinPreparation($domain->getDateFinPreparation());
        return $entity;
    }

    public function deleteById(int $commandeId): bool
    {
        $doctrineCommande = $this->find($commandeId);

        if ($doctrineCommande) {
            $this->entityManager->remove($doctrineCommande);
            $this->entityManager->flush();
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     * @return DomainCommandeCafe|null 
     */
    public function findLatestOne(): ?DomainCommandeCafe
    {
        /** @var DoctrineCommandeCafe|null $doctrineCommande */
        $doctrineCommande = $this->createQueryBuilder('c')
            ->orderBy('c.dateCreation', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        
        return $doctrineCommande ? $this->toDomain($doctrineCommande) : null;
    }

    /**
     * {@inheritdoc}
     * @return DomainCommandeCafe[]
     */
    public function findRecentByDateDesc(int $limit): array
    {
        /** @var DoctrineCommandeCafe[] $doctrineCommandes */
        $doctrineCommandes = $this->createQueryBuilder('c')
            ->orderBy('c.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        
        $domainCommandes = [];
        foreach ($doctrineCommandes as $doctrineCommande) {
            $domainCommandes[] = $this->toDomain($doctrineCommande);
        }
        return $domainCommandes;
    }
} 