<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use BM2\SiteBundle\Entity\PlaceType;
use BM2\SiteBundle\Entity\Place;

class PlaceManageType extends AbstractType {

	public function __construct($description, $isowner, Place $me) {
		$this->description = $description;
		$this->isOwner = $isowner;
		$this->me = $me;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'manageplace_1947',
			'translation_domain' => 'places'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$isOwner = $this->isOwner;
		$me = $this->me;
		$name = $me->getName();
		$formal = $me->getFormalName();
		$short = $me->getShortDescription();
		$description = $this->description;
		if ($isOwner) {
			$builder->add('name', 'text', array(
				'label'=>'names.name', 
				'required'=>true, 
				'data'=>$name,
				'attr' => array(
					'size'=>20, 
					'maxlength'=>40,
					'title'=>'help.new.name'
				)
			));
			$builder->add('formal_name', 'text', array(
				'label'=>'names.formalname', 
				'required'=>true, 
				'data'=>$formal,
				'attr' => array(
					'size'=>40, 
					'maxlength'=>160,
					'title'=>'help.new.formalname'
				)
			));
		}
		$builder->add('short_description', 'textarea', array(
			'label'=>'description.short',
			'data'=>$short,
			'attr' => array('title'=>'help.new.shortdesc'),
			'required'=>true,
		));
		$builder->add('description', 'textarea', array(
			'label'=>'description.full',
			'attr' => array('title'=>'help.new.longdesc'),
			'data'=>$description,
			'required'=>true,
		));
	}

	public function getName() {
		return 'placemanage';
	}
}
