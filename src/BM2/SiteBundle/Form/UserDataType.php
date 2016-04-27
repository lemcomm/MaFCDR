<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class UserDataType extends AbstractType {

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'userdata_2561',
			'data_class'		=> 'BM2\SiteBundle\Entity\User',
			'attr'				=> array('class'=>'wide')
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('username', null, array('label' => 'form.username', 'attr' => array('title' => 'registration.help.username', 'class'=>'tt_bot')));
		$builder->add('email', 'email', array('label' => 'form.email', 'attr' => array('title' => 'registration.help.email', 'class'=>'tt_bot')));

		$builder->add('display_name', null, array('label' => 'form.displayname', 'attr' => array('title' => 'form.help.displayname', 'class'=>'tt_bot')));

		$builder->add('submit', 'submit', array('label'=>'form.submit'));

	}

	public function getName() {
		return 'userdata';
	}
}
