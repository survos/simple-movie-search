<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Movie;
use ApiPlatform\Metadata\CollectionOperationInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Meilisearch\Bundle\SearchService;

class MeilliSearchStateProvider implements ProviderInterface
{
    public function __construct(
        private NormalizerInterface $normalizer,
        private SearchService $searchService    
    )
    {
  
    }
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {

            $searchQuery = isset($context['filters']['search'])?$context['filters']['search']:"";

            $filter = [];

            $this->getSortSequence($context, $filter);
            $this->getPaginationData($context, $filter);
            $this->getFilters($context, $filter);
            $this->getFacets($context, $filter);

            $objectData = $this->searchService->rawSearch($operation->getClass(), $searchQuery, $filter);
            return  $this->returnObject($objectData, $operation->getClass());
        }

        // Retrieve the state from somewhere
        return new Movie($uriVariables['id']);
    }

    private function returnObject(array $objectData, string $class) : object|array|null{
        $returnObject = [];
        foreach($objectData['hits'] as $hit) {
            $oject = new $class($hit);
            $methods = get_class_methods($oject);
            foreach ($methods as $method) {
                if (strpos($method, 'set') === 0) {
                    $variableName = strtolower(substr($method, 3));
                    $data = isset($hit[$variableName])?$hit[$variableName]:"";
                    if($variableName == 'imdbid' || $variableName == 'runtimeminutes') {
                        $data = (int) $data;
                    }
                    $oject->$method($data);
                }
            }
            $returnObject[] = $oject;
        }
        return $returnObject;
    }
    
    private function getSortSequence(array $context, array &$filter) {

        $sortByArray = [];
        if(isset($context['filters']['order\\'])) {
            foreach($context['filters']['order\\'] as $key => $value) {
                $sortByArray[] = rtrim($key,"\\").":".$value;
            }
        }
        if(isset($context['filters']['order'])) {
            foreach($context['filters']['order'] as $key => $value) {
                $sortByArray[] = rtrim($key,"\\").":".$value;
            }
        }

        $filter['sort'] = $sortByArray;
    }

    private function getPaginationData(array $context, array &$filter) {
       
        $filter['hitsPerPage'] = 30;
        if(isset($context['filters']['offset'])) {
            $filter['hitsPerPage'] = (int) $context['filters']['limit'];
        }

        $filter['offset'] = 0;
        if(isset($context['filters']['offset'])) {
            $filter['offset'] = (int) $context['filters']['offset'];
        }

//        $filter['page'] = 1;
        if(isset($context['filters']['page'])) {
            $pagination['page'] = (int) $context['filters']['page'];
        }
    }

    private function getFilters(array $context, array &$filter) {
        //'filter' => $filter,
    }

    private function getFacets(array $context, array &$filter) {
        //'facets' => ['year', 'type']
    }
}
