<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use BM2\SiteBundle\Entity\PlaceType;

class PlaceManageType extends AbstractType {

	public function __construct($types, $description, $new, $isowner) {
		$this->types = $types;
		$this->description = $description;
		$this->isNew = $new;
		$this->isOwner = $isowner;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'manageplace_1947',
			'translation_domain' => 'places'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$types = $this->types;
		$isNew = $this->isNew;
		$isOwner = $this->isOwner;
		$description = $this->description;
		if ($isOwner OR $isNew) {
			$builder->add('name', 'text', array(
				'label'=>'names.name', 
				'required'=>true, 
				'attr' => array(
					'size'=>20, 
					'maxlength'=>40,
					'title'=>'help.new.name'
				)
			));
			$builder->add('formal_name', 'text', array(
				'label'=>'names.formalname', 
				'required'=>true, 
				'attr' => array(
					'size'=>40, 
					'maxlength'=>160,
					'title'=>'help.new.formalname'
				)
			));
		}
		if ($isNew) {
			# This isn't going to work. Prep the list in the Controller, submit it through the form, then reapply it on the other side.
			$builder->add('type', 'entity', array(
				'label'=>'type.label',
				'required'=>true,
				'placeholder' => 'type.empty',
				'attr' => array('title'=>'help.new.type'),
				'class' => 'BM2SiteBundle:PlaceType',
				'choice_translation_domain' => true,
				'choice_label' => 'name',
				'choices' => $types,
				'group_by' => function($val, $key, $index) {
					if ($val->getRequires() == NULL) { 
						return 'by.none';
					} else {
						return 'by.'.$val->getRequires();
					}
				}
			));
		}
		$builder->add('short_description', 'textarea', array(
			'label'=>'description.short',
			'attr' => array('title'=>'help.new.shortdesc'),
			'required'=>true,
		));
		if ($description) {
			$builder->add('description', 'textarea', array(
				'label'=>'description.full',
				'attr' => array('title'=>'help.new.longdesc'),
				'data_class'=> NULL,
				'required'=>true,
			));
		} else { 
			$builder->add('description', 'textarea', array(
				'label'=>'description.full',
				'attr' => array('title'=>'help.new.longdesc'),
				'data'=>$description,
				'data_class'=> NULL,
				'required'=>true,
			));
		}
	}

	public function getName() {
		return 'placemanage';
	}
}
