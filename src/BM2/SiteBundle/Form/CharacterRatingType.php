<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class CharacterRatingType extends AbstractType {

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'characterrating_96515',
			'data_class'		=> 'BM2\SiteBundle\Entity\CharacterRating',
			'attr'				=> array('class'=>'wide')
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('character', 'hidden_entity', array(
			'required'=>true,
			'entity_repository'=>'BM2SiteBundle:Character'
		));
		$builder->add('content', 'textarea', array(
			'label'=>'rating.content',
			'required'=>true,
			'trim'=>true,
			'attr' => array('rows'=>3, 'maxChars'=>200)
		));
		$builder->add('respect', 'choice', array(
			'label'=>'rating.respect.label',
			'required'=>true,
			'choices'=>array('0'=>'rating.none', '1'=>'rating.yes', '-1'=>'rating.no'),
			'attr' => array('title'=>'rating.respect.help'),
		));
		$builder->add('honor', 'choice', array(
			'label'=>'rating.honor.label',
			'required'=>true,
			'choices'=>array('0'=>'rating.none', '1'=>'rating.yes', '-1'=>'rating.no'),
			'attr' => array('title'=>'rating.honor.help'),
		));
		$builder->add('trust', 'choice', array(
			'label'=>'rating.trust.label',
			'required'=>true,
			'choices'=>array('0'=>'rating.none', '1'=>'rating.yes', '-1'=>'rating.no'),
			'attr' => array('title'=>'rating.trust.help'),
		));

		$builder->add('submit', 'submit', array('label'=>'rating.submit'));
	}

	public function getName() {
		return 'characterrating';
	}
}
