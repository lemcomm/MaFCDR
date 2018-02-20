<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DescriptionNewType extends AbstractType {

	public function __construct($text) {
		$this->text = $text;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'newdescription_95315',
			'translation_domain' => 'actions'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$text = $this->text;
		$builder->add('text', 'textarea', array(
			'label'=>'control.description.full',
			'data'=>$text,
			'required'=>true,
		));
	}

	public function getName() {
		return 'descriptionnew';
	}
}
