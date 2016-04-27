<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CharacterBackgroundType extends AbstractType {

	private $alive;

	public function __construct($alive=true) {
		$this->alive = $alive;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'background_1651345',
			'translation_domain' => 'actions'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('appearance', 'textarea', array(
			'label'=>'meta.background.appearance',
			'trim'=>true,
			'required'=>false
		));
		$builder->add('personality', 'textarea', array(
			'label'=>'meta.background.personality',
			'trim'=>true,
			'required'=>false
		));
		$builder->add('secrets', 'textarea', array(
			'label'=>'meta.background.secrets',
			'trim'=>true,
			'required'=>false
		));

		if ($this->alive == false) {
			$builder->add('death', 'textarea', array(
				'label'=>'meta.background.death',
				'trim'=>true,
				'required'=>false
			));			
		}

		$builder->add('submit', 'submit', array('label'=>'meta.background.submit'));
	}

	public function getName() {
		return 'background';
	}
}
