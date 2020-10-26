<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use BM2\SiteBundle\Entity\PlaceType;

class PlaceNewType extends AbstractType {

	public function __construct($types) {
		$this->types = $types;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'newplace_1337',
			'translation_domain' => 'places'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$types = $this->types;
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
		$builder->add('realm', 'entity', array(
			'label'=>'realm.label',
			'required'=>false,
			'placeholder' => 'realm.empty',
			'attr' => array('title'=>'help.new.realm'),
			'class' => 'BM2SiteBundle:Realm',
			'choice_translation_domain' => true,
			'choice_label' => 'name',
			'choices' => $realms
		));
		$builder->add('short_description', 'textarea', array(
			'label'=>'description.short',
			'attr' => array('title'=>'help.new.shortdesc'),
			'required'=>true,
		));
		$builder->add('description', 'textarea', array(
			'label'=>'description.full',
			'attr' => array('title'=>'help.new.longdesc'),
			'required'=>true,
		));
	}

	public function getName() {
		return 'placenew';
	}
}
