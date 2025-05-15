<?php

namespace App\Domain\Entity;

use Symfony\Component\Serializer\Annotation\Groups;

class CommandeCafe
{
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUT_EN_COURS = 'EN_COURS';
    public const STATUT_TERMINEE = 'TERMINEE';
    public const STATUT_ANNULEE = 'ANNULEE';
    public const STATUT_ERREUR = 'ERREUR';

    // Types de café
    public const TYPE_ESPRESSO = 'ESPRESSO';
    public const TYPE_LUNGO = 'LUNGO';
    public const TYPE_CAPPUCCINO = 'CAPPUCCINO';
    public const TYPE_LATTE = 'LATTE';

    // Tailles de café
    public const TAILLE_PETIT = 'PETIT';
    public const TAILLE_MOYEN = 'MOYEN';
    public const TAILLE_GRAND = 'GRAND';

    #[Groups(['commande:read'])]
    private ?int $id = null;
    #[Groups(['commande:read'])]
    private string $type;
    #[Groups(['commande:read'])]
    private int $intensite;
    #[Groups(['commande:read'])]
    private string $taille;
    #[Groups(['commande:read'])]
    private string $statut;
    #[Groups(['commande:read'])]
    private ?\DateTimeImmutable $dateCreation = null;
    #[Groups(['commande:read'])]
    private ?\DateTimeImmutable $dateDebutPreparation = null;
    #[Groups(['commande:read'])]
    private ?\DateTimeImmutable $dateFinPreparation = null;

    public function __construct(
        string $type,
        int $intensite,
        string $taille,
        string $statut,
        ?\DateTimeImmutable $dateCreation,
        ?int $id = null,
        ?\DateTimeImmutable $dateDebutPreparation = null,
        ?\DateTimeImmutable $dateFinPreparation = null,
    ) {
        $this->type = $type;
        $this->intensite = $intensite;
        $this->taille = $taille;
        $this->statut = $statut;
        $this->dateCreation = $dateCreation;
        $this->id = $id;
        $this->dateDebutPreparation = $dateDebutPreparation;
        $this->dateFinPreparation = $dateFinPreparation;
    }

    public function getId(): ?int { return $this->id; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): void { $this->type = $type; }
    public function getIntensite(): int { return $this->intensite; }
    public function setIntensite(int $intensite): void { $this->intensite = $intensite; }
    public function getTaille(): string { return $this->taille; }
    public function setTaille(string $taille): void { $this->taille = $taille; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): void { $this->statut = $statut; }
    public function getDateCreation(): ?\DateTimeImmutable { return $this->dateCreation; }
    public function setDateCreation(?\DateTimeImmutable $dateCreation): void { $this->dateCreation = $dateCreation; }
    public function getDateDebutPreparation(): ?\DateTimeImmutable { return $this->dateDebutPreparation; }
    public function setDateDebutPreparation(?\DateTimeImmutable $dateDebutPreparation): void { $this->dateDebutPreparation = $dateDebutPreparation; }
    public function getDateFinPreparation(): ?\DateTimeImmutable { return $this->dateFinPreparation; }
    public function setDateFinPreparation(?\DateTimeImmutable $dateFinPreparation): void { $this->dateFinPreparation = $dateFinPreparation; }
    public function setId(int $id): void { $this->id = $id; }
    public function getDateCreationString(): string { return $this->dateCreation?->format(\DateTime::ATOM) ?? ''; }
    public function getDateDebutPreparationString(): ?string { return $this->dateDebutPreparation?->format(\DateTime::ATOM); }
    public function getDateFinPreparationString(): ?string { return $this->dateFinPreparation?->format(\DateTime::ATOM); }
} 