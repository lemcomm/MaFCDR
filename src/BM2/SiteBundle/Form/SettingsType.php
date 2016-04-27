<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class SettingsType extends AbstractType {

	private $user;
	private $languages;

	public function __construct($user, $languages) {
		$this->user = $user;
		$this->languages = $languages;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'	=> 'settings_41234',
			'attr'		=> array('class'=>'wide')
		));
	}

	// FIXME: change this to use the user object (it's very old code, I didn't know about it)
	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('notifications', 'checkbox', array(
			'label' => 'account.settings.notifications',
			'required' => false,			
			'data'=>$this->user->getNotifications()
		));
		$builder->add('newsletter', 'checkbox', array(
			'label' => 'account.settings.newsletter',
			'required' => false,			
			'data'=>$this->user->getNewsletter()
		));
		$builder->add('language', 'choice', array(
			'label' => 'account.settings.language',
			'placeholder' => 'form.browser',
			'required' => false,
			'choices' => $this->languages,
			'data'=>$this->user->getLanguage()
		));

		$builder->add('submit', 'submit', array('label'=>'account.settings.submit'));
	}

	public function getName() {
		return 'settings';
	}
}
