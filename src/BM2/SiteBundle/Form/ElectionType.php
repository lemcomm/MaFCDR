<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class ElectionType extends AbstractType {

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'election_23865',
			'translation_domain' => 'politics',
			'data_class'			=> 'BM2\SiteBundle\Entity\Election',
			'attr'					=> array('class'=>'wide')
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('name', 'text', array(
			'label'=>'elections.title',
			'required'=>true,
			'attr' => array('size'=>20, 'maxlength'=>40)
		));
		$builder->add('description', 'textarea', array(
			'label'=>'elections.desc',
			'required'=>true,
		));
		$builder->add('method', 'choice', array(
			'label'=>'elections.method.name',
			'placeholder'=>'elections.method.empty',
			'choice_translation_domain' => true,
			'choices' => array(
				'banner' => 'elections.method.banner',
				'spears' => 'elections.method.spears',
				'swords' => 'elections.method.swords',
				'horses' => 'elections.method.horses',
				'land'	=> 'elections.method.land',
				'realmland' => 'elections.method.realmland',
				'castles' => 'elections.method.castles',
				'realmcastles' => 'elections.method.realmcastles',
				'heads'	=> 'elections.method.heads',
			)
		));
		$builder->add('duration', 'choice', array(
			'label'=>'elections.duration.name',
			'placeholder'=>'elections.duration.empty',
			'mapped'=>false,
			'choice_translation_domain' => true,
			'choices' => array(
				1 => 'elections.duration.1',
				3 => 'elections.duration.3',
				5 => 'elections.duration.5',
				7 => 'elections.duration.7',
				10 => 'elections.duration.10',
			)
		));

		$builder->add('submit', 'submit', array('label'=>'elections.submit'));
	}

	public function getName() {
		return 'election';
	}
}
