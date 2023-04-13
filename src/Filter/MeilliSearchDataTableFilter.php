<?php

namespace App\Filter;

use ApiPlatform\Api\ResourceClassResolverInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use phpDocumentor\Reflection\Types\Boolean;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\PropertyInfo\Type;

final class MeilliSearchDataTableFilter extends AbstractMeilliSearchFilter implements MeilliSearchFilterInterface
{
    public function __construct(PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory, PropertyMetadataFactoryInterface $propertyMetadataFactory, ResourceClassResolverInterface $resourceClassResolver, ?NameConverterInterface $nameConverter = null, private readonly string $orderParameterName = 'filter', ?array $properties = null)
    {
        parent::__construct($propertyNameCollectionFactory, $propertyMetadataFactory, $resourceClassResolver, $nameConverter, $properties);
    } 

    public function apply(array $clauseBody, string $resourceClass, ?Operation $operation = null, array $context = []): array {
        if(isset($context['filters']['searchBuilder'])) {
            $filter = isset($clauseBody['filter'])? $clauseBody['filter'] :"";

            $searchBuilder = $context['filters']['searchBuilder'];
            $filter = $this->criteria($searchBuilder['logic'], $searchBuilder['criteria']);

            dd($filter);
            foreach($context['filters']['searchBuilder'] as $filterData) {
                //$filter = $this->recursiveFilter($filter, $filter);
            }
        }
        return $clauseBody;
    }

    private function logicCriteria(string $logic, array $criteria) {
        $name = $criteria[0]["origData"]." ".$criteria[0]["condition"]." ".$criteria[0]["value"][0]." ) ".$logic." ";
    }

    private function criteria(string $logic, array $criterias) {
        $query = " ( ";
        foreach($criterias as $criteria) {
            $query .= " ".$criteria["origData"]." ".$this->matchConditionWithName($criteria["condition"], $criteria["value"][0])." ".$logic;
        }
        $query = rtrim($query, $logic)." ) ";
        return $query;
    }

    public function getDescription(string $resourceClass): array
    {
        return [];
    }

    public function matchConditionWithName(string $condition, string|int|bool $value) {
        switch($condition) {
            case "=":
                return " = ".$value;
            case "!=":
                return " != ".$value;
            case "starts" :
                return " >= ".$value;
            case "!starts" :
                #todo
                return " >= ".$value;
            case "contains" :
                #todo 
                return "contains";
            case "!contains" :
                #todo
                return "!contains";
            case "ends" :
                #todo
                return " ends ".$value;
            case "!ends" :
                #todo
                return " ends ".$value;
            case "!ends" :
                #todo
                return " ends ".$value;
            case "null" :
                #todo
                return " = NULL";
            case "!null" :
                #todo
                return " != NULL";
            default:
                return $condition;
        }
    }
}