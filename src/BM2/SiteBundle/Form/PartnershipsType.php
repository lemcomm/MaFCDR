<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class PartnershipsType extends AbstractType {

	private $me;
	private $newpartners;
	private $others;

	public function __construct($me, $newpartners, $others) {
		$this->me = $me;
		$this->newpartners = $newpartners;
		$this->others = $others;
	}

	public function getName() {
		return 'partnership';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'partnership_5712',
			'translation_domain' => 'politics'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		if ($this->newpartners) {
			$this->buildFormNew($builder, $options);
		} else {
			$this->buildFormOld($builder, $options);
		}
	}

	public function buildFormNew(FormBuilderInterface $builder, array $options) {
		$types = array(
			'engagement' => 'relation.choice.engagement',
			'marriage' => 'relation.choice.marriage',
			'liason' => 'relation.choice.liason'
		);
		$builder->add('type', 'choice', array(
			'choices' => $types,
			'label' => 'relation.choice.type',
			'placeholder' => 'relation.choice.choose',
		));
		$builder->add('partner', 'choice', array(
			'choices' => $this->others,
			'label' => 'relation.choice.partner',
			'placeholder' => 'relation.choice.choose',
		));
		$builder->add('public', 'checkbox', array(
			'label' => 'relation.choice.public',
			'required' => false,
		));
		$builder->add('sex', 'checkbox', array(
			'label' => 'relation.choice.sex',
			'required' => false,
		));
		$builder->add('crest', 'checkbox', array(
			'label' => 'relation.choice.crest',
			'required' => false,
		));
	}

	public function buildFormOld(FormBuilderInterface $builder, array $options) {
		$builder->add('partnership', 'form');

		foreach ($this->others as $partnership) {
			if ($partnership->getActive()) {
				$label = 'relation.choice.change';
				$choices=array();
				if ($partnership->getPublic()==false) {
					$choices['public'] = 'relation.choice.makepublic';
				}
				if ($partnership->getWithSex()==true) {
					$choices['nosex'] = 'relation.choice.refusesex';
				}
				$choices['cancel'] = 'relation.choice.cancel';
			} else {
				if ($partnership->getInitiator() == $this->me) {
					$label = 'relation.choice.stay';
					$choices = array(
						'withdraw' => 'relation.choice.withdraw'
					);
				} else {
					$label = 'relation.choice.decide';
					$choices = array(
						'accept' => 'relation.choice.accept',
						'reject' => 'relation.choice.reject'
					);					
				}
			}
			$builder->get('partnership')->add(
				(string)$partnership->getId(),
				'choice', array(
					'choices' => $choices,
					'label' => $label,
					'placeholder' => 'relation.choice.nochange',
					'required' => false,
				)
			);

		}
	}
}
