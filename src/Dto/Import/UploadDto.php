<?php

namespace App\Dto\Import;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;

final class UploadDto
{
    #[Assert\NotNull(message: 'Sélectionne un fichier YAML.')]
    public ?UploadedFile $file = null;

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addConstraint(new Assert\Callback('validateFile'));
    }

    public function validateFile(ExecutionContextInterface $context): void
    {
        if (!$this->file instanceof UploadedFile) {
            return; // déjà géré par NotNull
        }

        // Taille max : 1 Mo
        if ($this->file->getSize() > 1_000_000) {
            $context->buildViolation('Le fichier ne doit pas dépasser 1 Mo.')
                ->atPath('file')
                ->addViolation();
            return;
        }

        // Extension : .yaml ou .yml
        $extension = strtolower($this->file->getClientOriginalExtension());
        if (!in_array($extension, ['yaml', 'yml'], true)) {
            $context->buildViolation('Le fichier doit avoir l\'extension .yaml ou .yml.')
                ->atPath('file')
                ->addViolation();
        }
    }
}
