<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class RealmPositionType extends AbstractType {

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'realmpositions_461234',
			'translation_domain' => 'politics',
			'data_class'			=> 'BM2\SiteBundle\Entity\RealmPosition',
			'attr'					=> array('class'=>'wide')
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		if ($options['data'] && $options['data']->getRuler()) {
			$is_ruler = true;
		} else {
			$is_ruler = false;
		}

		if (!$is_ruler) {
			$builder->add('name', 'text', array(
				'label'=>'position.name',
				'required'=>true,
				'attr' => array('size'=>20, 'maxlength'=>40)
			));
		}
		$builder->add('description', 'textarea', array(
			'label'=>'position.description',
			'required'=>true,
		));
		if (!$is_ruler) {
			$builder->add('permissions', 'entity', array(
				'label'=>'position.permissions',
				'required' => false,
				'multiple' => true,
				'expanded' => true,
				'choice_translation_domain' => true,
				'class'=>'BM2SiteBundle:Permission',
				'choice_label'=>'translation_string',
				'query_builder'=>function(EntityRepository $er) {
					return $er->createQueryBuilder('p')->where('p.class = :class')->setParameter('class', 'realm');
				}
			));
			$builder->add('elected', 'checkbox', array(
				'label'=>'position.elected',
				'required' => false,
				'attr' => array('title'=>'position.help.elected'),
			));
		}
		$builder->add('inherit', 'checkbox', array(
			'label'=>'position.inherit',
			'required' => false,
			'attr' => array('title'=>'position.help.inherit'),
		));
		$builder->add('term', 'choice', array(
			'label'=>'position.term',
			'choices' => array(
				0 => 'position.terms.0',
				365 => 'position.terms.365',
				90 => 'position.terms.90',
				30 => 'position.terms.30',
			)
		));

		$builder->add('submit', 'submit', array('label'=>'position.submit'));
	}

	public function getName() {
		return 'realmposition';
	}
}
