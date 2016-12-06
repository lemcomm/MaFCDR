<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HouseCreationType extends AbstractType {

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'housecreation_78315',
			'translation_domain' => 'politics'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('name', 'textfield', array(
			'label'=>'house.setup.name',
			'required'=>true,
			'attr' => array('size'=>30, 'maxlength'=>80, 'title'=>'newcharacter.help.name')
		));
		$builder->add('description', 'textarea', array(
			'label'=>'house.setup.description',
			'trim'=>true,
			'required'=>false
		));
		$builder->add('private_description', 'textarea', array(
			'label'=>'house.setup.private',
			'trim'=>true,
			'required'=>false
		));
		$builder->add('secret_description', 'textarea', array(
			'label'=>'house.setup.secret',
			'trim'=>true,
			'required'=>false
		));

		$builder->add('submit', 'submit', array('label'=>'house.setup.submit'));
	}

	# What the below actually do anyways? I've a feeling it's something to do with the automated testing tools, but I am not sure. --Andrew
	public function getName() {
		return 'housecreate';
	}
}
