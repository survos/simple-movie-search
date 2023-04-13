<?php

namespace App\Filter;

use ApiPlatform\Api\FilterInterface as BaseFilterInterface;
use ApiPlatform\Metadata\Operation;

interface MeilliSearchFilterInterface extends BaseFilterInterface
{

    public function apply(array $clauseBody, string $resourceClass, ?Operation $operation = null, array $context = []): array;
}