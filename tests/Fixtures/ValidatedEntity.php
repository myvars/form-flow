<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;

final class ValidatedEntity
{
    public function __construct(
        #[Assert\NotBlank(message: 'Please enter a name')]
        #[Assert\Length(max: 5, maxMessage: 'Max 5 characters')]
        public string $name = 'old',
    ) {
    }
}
