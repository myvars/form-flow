<?php

declare(strict_types=1);

namespace MyVars\FormFlow\Contract;

/**
 * FormFlow port for the paging/sort inputs a search flow needs.
 *
 * The app supplies the adapter (e.g. App\Shared\Application\Search\SearchCriteria),
 * whose own interface extends this one so existing consumers stay untouched.
 */
interface SearchCriteriaInterface
{
    public function getQuery(): ?string;

    public function getSort(): string;

    public function getSortDirection(): string;

    public function getLimit(): int;

    public function getPage(): int;
}
