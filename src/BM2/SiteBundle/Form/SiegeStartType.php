<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

use Symfony\Component\OptionsResolver\OptionsResolver;

class SiegeStartType extends AbstractType {

	private $settlement;
	private $places;

	public function __construct($settlement = null, $places = null) {
		$this->settlement = $settlement;
		$this->places = $places;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'	=> 'siegestart_9753',
			'translation_domain' => 'actions'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$settlement = $this->settlement;
		$places = $this->places;

		if ($settlement) {
			$builder->add('settlement', CheckboxType::class, array(
				'required'=>false,
				'label'=> 'military.siege.menu.confirm'
			));
		} else {
			$builder->add('settlement', HiddenType::class, array(
				'data'=>false
			));
		}

		if ($places) {
			$builder->add('place', ChoiceType::class, array(
				'required'=>false,
				'choices' => $places,
				'choice_label' => 'name',
				'placeholder'=>'military.siege.menu.none',
				'label'=> 'military.siege.menu.places'
			));
		} else {
			$builder->add('places', HiddenType::class, array(
				'data'=>false
			));
		}

		if ($settlement || $places) {
			$builder->add('submit', 'submit', array('label'=>'military.siege.submit'));
		}
	}

	public function getName() {
		return 'siegestart';
	}

}
