<?php

namespace BM2\SiteBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

use BM2\SiteBundle\Form\GeoResourceType;

class EditGeoDataType extends AbstractType
{

	public function __construct($food=null, $wood=null, $metal=null) {
		$this->food = $food;
		$this->wood = $wood;
		$this->metal = $metal;
        }

        public function buildForm(FormBuilderInterface $builder, array $options) {
                $builder
                        ->add('altitude')
                        ->add('hills')
                        ->add('coast')
                        ->add('lake')
                        ->add('river')
                        ->add('humidity')
                        ->add('passable')
                        ->add('settlement', EntityType::class, [
                        'class'=>'BM2SiteBundle:Settlement',
                        'label'=>'Settlement',
                        'choice_label'=>'name'
                        ])
                        ->add('biome', EntityType::class, [
                        'class'=>'BM2SiteBundle:Biome',
                        'label'=>'Biome',
                        'choice_label'=>'name'
                        ])
                        ->add('food', NumberType::class, [
        			'label'=>'Base Food',
        			'data'=>$this->food?$this->food:0,
        			'required'=>false,
                                'mapped'=>false,
                                'required'=>false
                        ])
                        ->add('wood', NumberType::class, [
        			'label'=>'Base Wood',
        			'data'=>$this->wood?$this->wood:0,
        			'required'=>false,
                                'mapped'=>false,
                                'required'=>false
                        ])
                        ->add('metal', NumberType::class, [
        			'label'=>'Base Metal',
        			'data'=>$this->metal?$this->metal:0,
        			'required'=>false,
                                'mapped'=>false,
                                'required'=>false
                        ]);

                $builder->add('submit', SubmitType::class)
                ;
        }

        public function setDefaultOptions(OptionsResolverInterface $resolver) {
                $resolver->setDefaults(array(
                        'data_class' => 'BM2\SiteBundle\Entity\GeoData'
                ));
        }

        public function getName() {
        return 'maf_geodata';
        }
}
