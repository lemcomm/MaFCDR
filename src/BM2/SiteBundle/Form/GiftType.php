<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class GiftType extends AbstractType {

	private $invite;
	private $credits;

	public function __construct($choices, $invite=false) {
		$this->invite = $invite;
		$this->credits = $choices;
	}

	public function getName() {
		return 'gift';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention' => 'gift_131',
			'attr'		=> array('class'=>'wide')
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('email', 'email', array(
			'label'=>'account.gift.email',
			'required'=>true,
			'attr' => array('size'=>40, 'maxlength'=>250)
		));

		$builder->add('credits', 'choice', array(
			'required'=>true, 
			'label'=>'account.gift.credits',
			'placeholder'=>'form.choose',
			'choices'=>$this->credits
		));

		$builder->add('message', 'textarea', array(
			'label'=>'account.gift.text',
			'trim'=>true,
			'required'=>false
		));

		if ($this->invite) {
			$submit = 'account.gift.invite';
		} else {
			$submit = 'account.gift.gift';
		}

		$builder->add('submit', 'submit', array(
			'label'=>$submit,
		));

	}


}
