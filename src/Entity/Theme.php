<?php

namespace App\Entity;

use App\Repository\ThemeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ThemeRepository::class)]
#[ORM\UniqueConstraint(name: 'theme_slug_unique', columns: ['slug'])]
class Theme
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private string $emoji;

    #[ORM\Column(length: 255)]
    private string $name;

    /**
     * @var Collection<int, RedFlag>
     */
    #[ORM\OneToMany(targetEntity: RedFlag::class, mappedBy: 'theme', orphanRemoval: true)]
    private Collection $redFlags;

    #[ORM\Column(length: 255, unique: true)]
    private string $slug;

    public function __construct()
    {
        $this->redFlags = new ArrayCollection();
    }

    public function addRedFlag(RedFlag $redFlag): static
    {
        if (!$this->redFlags->contains($redFlag)) {
            $this->redFlags->add($redFlag);
            $redFlag->setTheme($this);
        }

        return $this;
    }

    public function getEmoji(): string
    {
        return $this->emoji;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return Collection<int, RedFlag>
     */
    public function getRedFlags(): Collection
    {
        return $this->redFlags;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function removeRedFlag(RedFlag $redFlag): static
    {
        // orphanRemoval handles deletion once the flag leaves the collection.
        $this->redFlags->removeElement($redFlag);

        return $this;
    }

    public function setEmoji(string $emoji): static
    {
        $this->emoji = $emoji;

        return $this;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }
}
