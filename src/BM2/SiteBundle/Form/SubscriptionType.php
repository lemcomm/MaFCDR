<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class SubscriptionType extends AbstractType {

	private $all_levels;
	private $current_level;

	public function __construct($all_levels, $current_level) {
		$this->all_levels = $all_levels;
		$this->current_level = $current_level;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'subscription_145615',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$choices = array();
		foreach ($this->all_levels as $i=>$level) {
			if ($level["selectable"]) {
				$choices[$i] = 'account.level.'.$i;
			}
		}

		$builder->add('level', 'choice', array(
			'label' => 'account.level.name',
			'required' => true,
			'expanded' => true,
			'choices' => $choices,
			'data'=>$this->current_level
		));
	}

	public function getName() {
		return 'subscription';
	}
}
