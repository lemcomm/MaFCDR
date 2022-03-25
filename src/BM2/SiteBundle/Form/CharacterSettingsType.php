<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use BM2\SiteBundle\Entity\CharacterSettings;

class CharacterSettingsType extends AbstractType {

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'charactersettings_671',
			'translation_domain' => 'settings',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('auto_read_realms', 'checkbox', array(
			'label'=>'character.auto.readrealms',
			'required'=>false,
		));
		$builder->add('auto_read_assocs', 'checkbox', array(
			'label'=>'character.auto.readassocs',
			'required'=>false,
		));
		$builder->add('auto_read_house', 'checkbox', array(
			'label'=>'character.auto.readhouse',
			'required'=>false,
		));
		$builder->add('non_hetero_options', 'checkbox', array(
			'label'=>'character.non_hetero_options',
			'required'=>false,
		));
		$builder->add('submit', 'submit', array('label'=>'submit'));
	}

	public function getName() {
		return 'charactersettings';
	}

}
