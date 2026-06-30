<?php

namespace App\Entity;

use App\Repository\BingoCardRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BingoCardRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\UniqueConstraint(name: 'bingo_card_slug_unique', columns: ['slug'])]
#[ORM\Index(name: 'idx_bingo_card_bingo_reached_at', columns: ['bingo_reached_at'])]
#[ORM\Index(name: 'idx_bingo_card_created_at', columns: ['created_at'])]
class BingoCard
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $bingoReachedAt = null;

    /**
     * Les 25 identifiants de red flags, dans l'ordre de la grille.
     *
     * @var list<int>
     */
    #[ORM\Column(type: 'json')]
    private array $cells = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * Les positions (0-24) des cases cochées.
     *
     * @var list<int>
     */
    #[ORM\Column(type: 'json')]
    private array $markedCells = [];

    #[ORM\Column(length: 50, unique: true)]
    private string $slug;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Theme $theme;

    public function getBingoReachedAt(): ?\DateTimeImmutable
    {
        return $this->bingoReachedAt;
    }

    /**
     * @return list<int>
     */
    public function getCells(): array
    {
        return $this->cells;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return list<int>
     */
    public function getMarkedCells(): array
    {
        return $this->markedCells;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getTheme(): Theme
    {
        return $this->theme;
    }

    public function hasReachedBingo(): bool
    {
        return null !== $this->bingoReachedAt;
    }

    public function setBingoReachedAt(?\DateTimeImmutable $bingoReachedAt): static
    {
        $this->bingoReachedAt = $bingoReachedAt;

        return $this;
    }

    /**
     * @param list<int> $cells
     */
    public function setCells(array $cells): static
    {
        $this->cells = $cells;

        return $this;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @param list<int> $markedCells
     */
    public function setMarkedCells(array $markedCells): static
    {
        $this->markedCells = $markedCells;

        return $this;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function setTheme(Theme $theme): static
    {
        $this->theme = $theme;

        return $this;
    }
}
