<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RealmCreationType extends AbstractType {

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'newrealm_1845',
			'translation_domain' => 'politics'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('name', 'text', array('label'=>'realm.name', 'required'=>true, 'attr' => array('size'=>20, 'maxlength'=>40)));
		$builder->add('formal_name', 'text', array('label'=>'realm.formalname', 'required'=>true, 'attr' => array('size'=>40, 'maxlength'=>160)));

		$realmtypes = array();
		for ($i=1;$i<7;$i++) {
			$realmtypes[$i] = 'realm.type.'.$i;
		}

		$builder->add('type', 'choice', array(
			'required'=>true, 
			'choices' => $realmtypes,
			'label'=> 'realm.designation',
			'placeholder' => 'realm.new.choose',
		));

	}

	public function getName() {
		return 'realmcreation';
	}
}
