<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class UserDataType extends AbstractType {

	private $gm;
	private $text;
	private $admin;

	public function __construct($gm, $text, $admin) {
		$this->gm = $gm;
		$this->text = $text;
		$this->admin = $admin;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'userdata_2561',
			'data_class'		=> 'BM2\SiteBundle\Entity\User',
			'attr'				=> array('class'=>'wide')
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('username', null, array('label' => 'form.username', 'attr' => array('title' => 'registration.help.username', 'class'=>'tt_bot')));
		$builder->add('email', EmailType::class, array('label' => 'form.email', 'attr' => array('title' => 'registration.help.email', 'class'=>'tt_bot')));

		$builder->add('display_name', null, array('label' => 'form.displayname', 'attr' => array('title' => 'form.help.displayname', 'class'=>'tt_bot')));
		if ($this->gm) {
			$builder->add('gm_name', null, array('label' => 'form.gmname', 'attr' => array('title' => 'form.help.gmname', 'class'=>'tt_bot')));
		} else {
			$builder->add('gm_name', HiddenType::class, array('data' => null, 'required'=>false));
		}
		if ($this->admin) {
			$builder->add('public_admin', null, array('label' => 'form.publicadmin', 'attr' => array('title' => 'form.help.publicadmin', 'class'=>'tt_bot')));
		} else {
			$builder->add('public_admin', HiddenType::class, array('data' => null, 'required'=>false));
		}
		$builder->add('public', null, [
			'label' => 'form.public',
			'attr' => [
				'title'=>'form.help.public',
				'class'=>'tt_bot'
			]
		]);
		$builder->add('text', TextareaType::class, [
			'label'=>'form.profile',
			'data'=>$this->text,
			'mapped'=>false,
			'required'=>true,
			'attr' => [
				'title'=>'form.help.profile',
				'class'=>'tt_bot'
			]
		]);

		$builder->add('submit', 'submit', array('label'=>'form.submit'));
	}

	public function getName() {
		return 'userdata';
	}
}
