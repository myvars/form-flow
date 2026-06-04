<?php

namespace MyVars\FormFlow\Mapper;

use Symfony\Component\ObjectMapper\ObjectMapperInterface;

/**
 * Requires symfony/object-mapper.
 */
final readonly class ObjectMapper
{
    /**
     * @param class-string<object> $targetClass
     */
    public function __construct(
        private ObjectMapperInterface $mapper,
        private string $targetClass,
    ) {
    }

    public function __invoke(mixed $data): object
    {
        // If already the target type, return as-is.
        if ($data instanceof $this->targetClass) {
            return $data;
        }

        // Let ObjectMapper handle array|object -> object mapping.
        return $this->mapper->map($data, $this->targetClass);
    }
}
