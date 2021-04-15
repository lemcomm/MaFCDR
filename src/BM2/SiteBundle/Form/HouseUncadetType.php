<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HouseUncadetType extends AbstractType {

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'	=> 'houseuncadet_1',
			'translation_domain' => 'politics'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('subject', TextType::class, array(
			'label' => 'house.uncadet.subject',
			'required' => true
		));
		$builder->add('text', TextareaType::class, array(
			'label' => 'house.uncadet.text',
			'required' => true
		));
		$builder->add('sure', CheckboxType::class, array(
			'label' => 'settings.areyousure',
			'translation_domain' => 'settings',
			'required' => true
		));

		$builder->add('submit', SubmitType::class, array('label'=>'requet.generic.submit', 'translation_domain' => 'actions'));
	}

	public function getName() {
		return 'houseuncadet';
	}
}
