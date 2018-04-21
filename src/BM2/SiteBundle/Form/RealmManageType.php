<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class RealmManageType extends AbstractType {

	private $min;
	private $max;

	public function __construct($min, $max) {
		$this->min = $min+1;
		if ($max > 0) {
			$this->max = $max-1;
		} else {
			$this->max = 7;
		}
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'realmmanage_13535',
			'translation_domain' => 'politics',
			'data_class'		=> 'BM2\SiteBundle\Entity\Realm',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('name', 'text', array(
			'label'=>'realm.name',
			'required'=>true,
			'attr' => array('size'=>20, 'maxlength'=>40)
		));
		$builder->add('formal_name', 'text', array(
			'label'=>'realm.formalname',
			'required'=>true,
			'attr' => array('size'=>40, 'maxlength'=>160)
		));
		$builder->add('colour_hex', 'text', array(
			'label'=>'realm.colour',
			'required'=>true,
			'attr' => array('size'=>7, 'maxlength'=>7)
		));
		$builder->add('colour_rgb', 'hidden');

		$builder->add('language', 'text', array(
			'label'=>'realm.language',
			'required'=>false
		));

		$realmtypes = array();
		for ($i=$this->min;$i<=$this->max;$i++) {
			$realmtypes[$i] = 'realm.type.'.$i;
		}

		$builder->add('type', 'choice', array(
			'required'=>true,
			'choices' => $realmtypes,
			'label'=> 'realm.designation',
		));

		/*
		$builder->add('old_description', 'textarea', array(
			'label'=>'realm.description',
			'required'=>false
		));
		*/

		$builder->add('submit', 'submit', array('label'=>'realm.manage.submit'));

	}

	public function getName() {
		return 'realmmanage';
	}
}
