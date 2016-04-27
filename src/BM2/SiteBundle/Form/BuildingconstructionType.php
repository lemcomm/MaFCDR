<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class BuildingconstructionType extends AbstractType {

	private $existing;
	private $available;

	public function __construct($existing, $available) {
		$this->existing = $existing;
		$this->available = $available;
	}

	public function getName() {
		return 'buildingconstruction';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'buildingconstruction_144',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('existing', 'form');
		foreach ($this->existing as $existing) {
			$builder->get('existing')->add(
				(string)$existing->getId(),
				'percent',
				array(
					'required' => false,
					'precision' => 2,
					'data' => $existing->getWorkers(),
					'attr' => array('size'=>3, 'class' => 'assignment')
				)
			);
		}

		$builder->add('available', 'form');
		foreach ($this->available as $available) {
			$builder->get('available')->add(
				(string)$available['id'],
				'percent',
				array(
					'required' => false,
					'precision' => 2,
					'attr' => array('size'=>3, 'class' => 'assignment')
				)
			);
		}
	}


}
