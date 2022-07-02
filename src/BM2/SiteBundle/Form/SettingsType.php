<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
		$builder->add('newsletter', CheckboxType::class, array(
			'label' => 'account.settings.newsletter',
			'required' => false,
			'data'=>$this->user->getNewsletter()
		));
		$builder->add('notifications', CheckboxType::class, array(
			'label' => 'account.settings.notifications',
			'required' => false,
			'data'=>$this->user->getNotifications()
		));
		$builder->add('emailDelay', ChoiceType::class, array(
			'label' => 'account.settings.delay.name',
			'required' => false,
			'placeholder' => 'account.settings.delay.choose',
			'choices'=>[
				'now' => 'account.settings.delay.now',
				'hourly' => 'account.settings.delay.hourly',
				'6h' => 'account.settings.delay.6h',
				'12h' => 'account.settings.delay.12h',
				'daily' => 'account.settings.delay.daily',
				'sundays' => 'account.settings.delay.sundays',
				'mondays' => 'account.settings.delay.mondays',
				'tuesdays' => 'account.settings.delay.tuesdays',
				'wednesdays' => 'account.settings.delay.wednesdays',
				'thursdays' => 'account.settings.delay.thursdays',
				'fridays' => 'account.settings.delay.fridays',
				'saturdays' => 'account.settings.delay.saturdays',
			],
			'data'=>$this->user->getEmailDelay()
		));
		$builder->add('language', ChoiceType::class, array(
			'label' => 'account.settings.language',
			'placeholder' => 'form.browser',
			'required' => false,
			'choices' => $this->languages,
			'data'=>$this->user->getLanguage()
		));

		$builder->add('submit', SubmitType::class, array('label'=>'account.settings.submit'));
	}

	public function getName() {
		return 'settings';
	}
}
