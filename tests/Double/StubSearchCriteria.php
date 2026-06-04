<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Tests\Double;

use MyVars\FormFlow\Contract\SearchCriteriaInterface;

/**
 * Test adapter for the SearchCriteriaInterface port.
 */
final readonly class StubSearchCriteria implements SearchCriteriaInterface
{
    public const int PAGE_DEFAULT = 1;

    public function __construct(
        private int $page = self::PAGE_DEFAULT,
        private int $limit = 10,
        private ?string $query = null,
        private string $sort = 'id',
        private string $direction = 'ASC',
    ) {
    }

    public function getQuery(): ?string
    {
        return $this->query;
    }

    public function getSort(): string
    {
        return $this->sort;
    }

    public function getSortDirection(): string
    {
        return $this->direction;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getPage(): int
    {
        return $this->page;
    }
}
