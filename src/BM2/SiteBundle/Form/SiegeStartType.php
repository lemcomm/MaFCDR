<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SiegeStartType extends AbstractType {

	public function __construct($settlement, $place) {
		$this->settlement = $settlement;
		$this->place = $place;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'	=> 'siegestart_9753',
			'translation_domain' => 'actions'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$options = array($settlement, $place);
		$builder->add('target', ChoiceType::class, array(
			'required'=>true,
			'choices' => $options,
			'placeholder'=>'military.siege.locations.none',
			'label'=> 'military.siege.locations.name'
		));
		$builder->add('sure', CheckboxType::class, array(
			'label' => 'areyousure',
			'required' => true
		));

		$builder->add('submit', 'submit', array('label'=>'button.submit'));
	}

	public function getName() {
		return 'siegestart';
	}

}
