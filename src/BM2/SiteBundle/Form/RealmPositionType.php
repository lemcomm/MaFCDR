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
			/*$builder->add('permissions', 'entity', array(
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
			));*/
			$builder->add('type', 'entity', array(
				'label' => 'position.type',
				'required' => false,
				'placeholder' => 'position.help.none',
				'attr' => array('title'=>'position.help.type'),
				'class' => 'BM2SiteBundle:PositionType',
				'choice_translation_domain' => true,
				'choice_label' => 'name',
				'query_builder' => function(EntityRepository $er) {
					return $er->createQueryBuilder('p')->where('p.id > 0')->orderBy('p.name');
				}
			));
			/*$builder->add('rank', 'number', array(
				'label'=>'position.rank',
				'required' => false,
				'empty_data' => '1',
				'attr' => array('title'=>'position.help.rank'),
			));*/
			$builder->add('retired', 'checkbox', array(
				'label'=>'position.retired',
				'required' => false,
				'attr' => array('title'=>'position.help.retired'),
			));
			$builder->add('legislative', 'checkbox', array(
				'label'=>'position.legislative',
				'required' => false,
				'attr' => array('title'=>'position.help.legislative'),
			));
			$builder->add('have_vassals', 'checkbox', array(
				'label'=>'position.haveVassals',
				'required' => false,
				'attr' => array('title'=>'position.help.haveVassals'),
			));
		}
		$builder->add('minholders', 'integer', array(
			'label'=>'position.minholders',
			'scale'=>0,
			'required' => false,
			'empty_data' => '1',
			'attr' => array('title'=>'position.help.minholders'),
		));
		$builder->add('inherit', 'checkbox', array(
			'label'=>'position.inherit',
			'required' => false,
			'attr' => array('title'=>'position.help.inherit'),
		));
		if (!$is_ruler) {
			$builder->add('elected', 'checkbox', array(
				'label'=>'position.elected',
				'required' => false,
				'attr' => array('title'=>'position.help.elected'),
			));
		}
		$builder->add('electiontype', 'choice', array(
			'label'=>'elections.method.name',
			'placeholder'=>'elections.method.empty',
			'choice_translation_domain' => true,
			'required' => false,
			'choices' => array(
				'banner' => 'elections.method.banner',
				'spears' => 'elections.method.spears',
				'swords' => 'elections.method.swords',
				'horses' => 'elections.method.horses',
				'land'	=> 'elections.method.land',
				'realmland' => 'elections.method.realmland',
				'castles' => 'elections.method.castles',
				'realmcastles' => 'elections.method.realmcastles',
				'heads'	=> 'elections.method.heads',
			),
			'attr' => array('title'=>'position.help.electiontype'),
		));
		$builder->add('term', 'choice', array(
			'label'=>'position.term',
			'choices' => array(
				0 => 'position.terms.0',
				365 => 'position.terms.365',
				90 => 'position.terms.90',
				30 => 'position.terms.30',
			),
			'attr' => array('title'=>'position.help.term'),
		));
		$builder->add('year', 'integer', array(
			'label'=>'position.year',
			'scale'=>0,
			'required' => false,
			'empty_data' => '1',
			'attr' => array('title'=>'position.help.year'),
		));
		$builder->add('week', 'integer', array(
			'label'=>'position.week',
			'scale'=>0,
			'required' => false,
			'empty_data' => '1',
			'attr' => array('title'=>'position.help.week'),
		));
		if (!$is_ruler) {
			$builder->add('keeponslumber', 'checkbox', array(
				'label'=>'position.keeponslumber',
				'required' => false,
				'attr' => array('title'=>'position.help.keeponslumber'),
			));
		}
		$builder->add('submit', 'submit', array('label'=>'position.submit'));
	}

	public function getName() {
		return 'realmposition';
	}

}
