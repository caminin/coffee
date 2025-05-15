<?php

namespace App\Infrastructure\Doctrine\Entity;

use App\Repository\CommandeCafeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Infrastructure\Doctrine\Repository\CommandeCafeRepository::class)]
class CommandeCafe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column]
    private ?int $intensite = null;

    #[ORM\Column(length: 20)]
    private ?string $taille = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateDebutPreparation = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateFinPreparation = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getIntensite(): ?int
    {
        return $this->intensite;
    }

    public function setIntensite(int $intensite): static
    {
        $this->intensite = $intensite;

        return $this;
    }

    public function getTaille(): ?string
    {
        return $this->taille;
    }

    public function setTaille(string $taille): static
    {
        $this->taille = $taille;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeImmutable $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getDateDebutPreparation(): ?\DateTimeImmutable
    {
        return $this->dateDebutPreparation;
    }

    public function setDateDebutPreparation(?\DateTimeImmutable $dateDebutPreparation): static
    {
        $this->dateDebutPreparation = $dateDebutPreparation;

        return $this;
    }

    public function getDateFinPreparation(): ?\DateTimeImmutable
    {
        return $this->dateFinPreparation;
    }

    public function setDateFinPreparation(?\DateTimeImmutable $dateFinPreparation): static
    {
        $this->dateFinPreparation = $dateFinPreparation;

        return $this;
    }
} 