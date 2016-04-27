<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class EntourageRecruitType extends AbstractType {

	private $recruits;

	public function __construct($recruits) {
		$this->recruits = $recruits;
	}

	public function getName() {
		return 'recruitment';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'recruit_23469',
			'translation_domain' => 'actions'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('recruits', 'form');

		foreach ($this->recruits as $recruit) {
			$builder->get('recruits')->add(
				(string)$recruit['type']->getId(),
				'integer',
				array(
					'required' => false,
					'attr' => array(
						'size'=>3, 'maxlength'=>3,
						'min'=>0,
						'class'=>'recruitment',
						'data-time'=>method_exists($recruit['type'], 'getTrainingRequired')?$recruit['type']->getTrainingRequired():0
					)
				)
			);
		}
	}


}
