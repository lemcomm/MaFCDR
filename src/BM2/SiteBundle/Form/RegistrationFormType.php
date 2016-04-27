<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class RegistrationFormType extends AbstractType {

	private $class;

	/**
	 * @param string $class The User class name
	 */
	public function __construct($class) {
		$this->class = $class;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'data_class' => $this->class,
			'intention'  => 'registration_1234',
		));
	}


	public function buildForm(FormBuilderInterface $builder, array $options) {
		parent::buildForm($builder, $options);

		$builder->add('username', null, array('label' => 'form.username', 'attr' => array('title'=>'registration.help.username')));
		$builder->add('email', 'email', array('label' => 'form.email', 'attr' => array('title'=>'registration.help.email')));
		$builder->add('plainPassword', 'repeated', array(
			'type' => 'password',
			'first_options' => array('label' => 'form.password', 'attr' => array('title'=>'registration.help.password')),
			'second_options' => array('label' => 'form.password_confirmation', 'attr' => array('title'=>'registration.help.repeat'),),
			'invalid_message' => 'fos_user.password.mismatch',
			));
	}

	public function getName() {
		return 'registration';
	}

 }

