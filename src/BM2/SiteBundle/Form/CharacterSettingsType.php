<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class RealmPositionType extends AbstractType {

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'charactersettings_671',
			'translation_domain' => 'settings',
			'data_class'			=> 'BM2\SiteBundle\Entity\CharacterSettings',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('auto_read_realms', 'checkbox', array(
			'label'=>'character.auto.readrealms',
			'required'=>false,
		));
		$builder->add('submit', 'submit', array('label'=>'submit'));
	}

	public function getName() {
		return 'charactersettings';
	}
			
}
