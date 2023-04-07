<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;

class MovieSearchType extends AbstractType
{
    private array $type = [];
    private array $year = [];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->setOptions($options['facets']);
        $builder
            ->add('from_year', ChoiceType::class, [
                'choices'  => ['select' => ""] + $this->year,
                'data' =>  $options['default_values']['from'],
                'required' => false
            ], ['attr'=> ['class' => 'form-control'], ])

            ->add('to_year', ChoiceType::class, [
                'choices'  => ['select' => ""] + $this->year,
                'data' =>  $options['default_values']['to'],
                'required' => false
            ], ['attr'=> ['class' => 'form-control']])

            ->add('search', null, ['data' =>  $options['default_values']['search'] ,'attr'=> ['class' => 'form-control'], 'required' => false])

            ->add('type', ChoiceType::class, [
                'choices'  => ['select' => ""] + $this->type,
                'expanded' => true,
                'data' =>  $options['default_values']['type'],
                'required' => false
            ],['attr'=> ['class' => 'form-control']])

            ->add('sortby', ChoiceType::class, [
                'choices'  => [
                    'year' => 'year',
                    'primaryTitle' => 'primaryTitle',
                    'type' => 'type'
                ],
                'data' =>  $options['default_values']['sortby'],
                'required' => false
            ],['attr'=> ['class' => 'form-control']])

            ->add('direction', ChoiceType::class, [
                'choices'  => [
                    'asc' => 'asc',
                    'desc' => 'desc'
                ],
                'data' =>  $options['default_values']['direction'],
                'required' => false
            ],['attr'=> ['class' => 'form-control']])

            ->add('submit', SubmitType::class)
            ->add('reset', ResetType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'facets' => [],
            'default_values' => []
        ]);
    }

    private function setOptions(array $data) {
        foreach($data as $key => $facets) {
            $this->setOptionChoice($key, $facets);
        }
    }

    private function setOptionChoice(string $key, array $facets) {
        foreach($facets as $facet => $count) {
            $this->$key[$facet.' ('.$count.')'] = $facet;
        }
    }
}
