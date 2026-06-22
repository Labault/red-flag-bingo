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
    private ?string $emoji = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, RedFlag>
     */
    #[ORM\OneToMany(targetEntity: RedFlag::class, mappedBy: 'theme', orphanRemoval: true)]
    private Collection $redFlags;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

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

    public function getEmoji(): ?string
    {
        return $this->emoji;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function removeRedFlag(RedFlag $redFlag): static
    {
        if ($this->redFlags->removeElement($redFlag)) {
            // set the owning side to null (unless already changed)
            if ($redFlag->getTheme() === $this) {
                $redFlag->setTheme(null);
            }
        }

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
