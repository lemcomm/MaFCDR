<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class ListSelectType extends AbstractType {

	private $character_groups = array(1,2,3,4, 21,22,23,29, 41,42,43,44,45,46,47, 71);


	public function getName() {
		return 'listselect';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention' => 'listselect_19273',
			'attr'		=> array('class'=>'tall')
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$groups = array();
		foreach ($this->character_groups as $id) {
			$groups[$id] = 'character.list.'.$id;
		}
		$builder->add('char', 'hidden');
		$builder->add('list', 'choice', array(
			'label'=>'character.list.select',
			'expanded'=>true,
			'required'=>true,
			'empty_data'=>1,
			'choices'=>$groups
		));
		$builder->add('submit', 'submit', array('label'=>'character.list.submit'));
	}

}
