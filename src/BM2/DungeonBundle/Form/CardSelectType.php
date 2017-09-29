<?php

namespace BM2\DungeonBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class CardSelectType extends AbstractType {

	public function getName() {
		return 'cardselect';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'cardselect_136',
			'translation_domain' => 'dungeons'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('card', 'number', array(
			'label' => false,
			'required' => true,
		));
	}

}
