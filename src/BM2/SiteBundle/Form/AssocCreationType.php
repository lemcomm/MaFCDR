<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use BM2\SiteBundle\Entity\AssociationType;

class AssocCreationType extends AbstractType {

	private $types;

	public function __construct($types) {
		$this->types = $types;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'newassoc_1779',
			'translation_domain' => 'orgs'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$types = $this->types;
		$builder->add('name', 'text', array(
			'label'=>'assoc.form.new.name',
			'required'=>true,
			'attr' => array(
				'size'=>20,
				'maxlength'=>40,
				'title'=>'assoc.help.name'
			)
		));
		$builder->add('formal_name', 'text', array(
			'label'=>'assoc.form.new.formalname',
			'required'=>true,
			'attr' => array(
				'size'=>40,
				'maxlength'=>160,
				'title'=>'assoc.help.formalname'
			)
		));
		$builder->add('type', 'entity', array(
			'label'=>'assoc.form.new.type',
			'required'=>true,
			'placeholder' => 'assoc.form.select',
			'attr' => array('title'=>'assoc.help.type'),
			'class' => 'BM2SiteBundle:AssociationType',
			'choice_translation_domain' => true,
			'choice_label' => 'name',
			'choices' => $types
		));
		$builder->add('motto', 'text', array(
			'label'=>'assoc.form.new.motto',
			'required'=>false,
			'attr' => array(
				'size'=>40,
				'maxlength'=>160,
				'title'=>'assoc.help.motto')
		));
		$builder->add('founder', 'text', array(
			'label'=>'assoc.form.new.founder',
			'required'=>true,
			'attr' => array(
				'size'=>20,
				'maxlength'=>160,
				'title'=>'assoc.help.founder'
			)
		));
		$builder->add('public', 'checkbox', array(
			'label'=>'assoc.form.new.public',
			'attr' => array('title'=>'assoc.help.public'),
			'data' => true
		));
		$builder->add('short_description', 'textarea', array(
			'label'=>'assoc.form.description.short',
			'attr' => array('title'=>'assoc.help.shortdesc'),
			'required'=>true,
		));
		$builder->add('description', 'textarea', array(
			'label'=>'assoc.form.description.full',
			'attr' => array('title'=>'assoc.help.longdesc'),
			'required'=>true,
		));
		$builder->add('submit', 'submit', array('label'=>'assoc.form.submit'));
	}

	public function getName() {
		return 'assoccreate';
	}
}
