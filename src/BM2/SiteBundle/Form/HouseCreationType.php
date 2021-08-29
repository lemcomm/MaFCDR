<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HouseCreationType extends AbstractType {

	private $house;
	
	public function __construct($name = NULL, $motto = NULL, $desc = NULL, $priv = NULL, $secret = NULL) {
		$this->name = $name;
		$this->motto = $motto;
		$this->desc = $desc;
		$this->priv = $priv;
		$this->secret = $secret;
	}		
	
	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'housecreation_78315',
			'translation_domain' => 'politics'
		));
	}
	
	public function buildForm(FormBuilderInterface $builder, array $options) {
		$name = $this->name;
		$motto = $this->motto;
		$desc = $this->desc;
		$priv = $this->priv;
		$secret = $this->secret;
		$builder->add('name', 'text', array(
			'label'=>'house.create.name',
			'required'=>true,
			'data'=>$name,
			'attr' => array('size'=>30, 'maxlength'=>80)
		));
		$builder->add('motto', 'text', array(
			'label'=>'house.create.motto',
			'required'=>false,
			'data'=>$motto,
			'attr' => array('size'=>30, 'maxlength'=>200)
		));
		$builder->add('description', 'textarea', array(
			'label'=>'house.create.description',
			'trim'=>true,
			'required'=>false,
			'data'=>$desc
		));
		$builder->add('private', 'textarea', array(
			'label'=>'house.create.private',
			'trim'=>true,
			'required'=>false,
			'data'=>$priv
		));
		$builder->add('secret', 'textarea', array(
			'label'=>'house.create.secret',
			'trim'=>true,
			'required'=>false,
			'data'=>$secret
		));

		$builder->add('submit', 'submit', array('label'=>'house.create.submit'));
	}
	
	public function getName() {
		return 'housecreate';
	}
}
