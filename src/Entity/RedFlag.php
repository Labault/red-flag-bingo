<?php

namespace App\Entity;

use App\Enum\Rarity;
use App\Repository\RedFlagRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RedFlagRepository::class)]
#[ORM\Index(name: 'idx_red_flag_archived_at', columns: ['archived_at'])]
class RedFlag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    #[ORM\Column(length: 20, enumType: Rarity::class)]
    private ?Rarity $rarity = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $text = null;

    #[ORM\ManyToOne(inversedBy: 'redFlags')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Theme $theme = null;

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRarity(): ?Rarity
    {
        return $this->rarity;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function getTheme(): ?Theme
    {
        return $this->theme;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    public function setArchivedAt(?\DateTimeImmutable $archivedAt): static
    {
        $this->archivedAt = $archivedAt;

        return $this;
    }

    public function setRarity(Rarity $rarity): static
    {
        $this->rarity = $rarity;

        return $this;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function setTheme(?Theme $theme): static
    {
        $this->theme = $theme;

        return $this;
    }
}
