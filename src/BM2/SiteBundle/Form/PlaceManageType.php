<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlaceManageType extends AbstractType {

	public function __construct($types, $description, $new) {
		$this->types = $types;
		$this->description = $description;
		$this->new = $new;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'manageplace_1947',
			'translation_domain' => 'places'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('name', 'text', array(
			'label'=>'names.name', 
			'required'=>true, 
			'attr' => array(
				'size'=>20, 
				'maxlength'=>40
			)
		));
		$builder->add('formal_name', 'text', array(
			'label'=>'names.formalname', 
			'required'=>true, 
			'attr' => array(
				'size'=>40, 
				'maxlength'=>160
			)
		));
		if ($new) {
			$builder->add('type', 'choice', array(
				'label'=>'type.label',
				'required'=>true,
				'placeholder'=>'type.empty',
				'choices' => $types,
				'class' =>'BM2SiteBundle:PlaceType',
				'choice_label'=>'name'
			));
		}
		$builder->add('description', 'textarea', array(
			'label'=>'description.full',
			'class'=> NULL,
			'placeholder'=>$description,
			'required'=>true,
		));
	}

	public function getName() {
		return 'placemanage';
	}
}
