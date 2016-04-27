<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class SetMarkerType extends AbstractType {

	private $realms;

	public function __construct($realms) {
		$this->realms = $realms;
	}


	public function getName() {
		return 'setmarker';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'				=> 'setmarker_19283',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('name', 'text', array(
			'label'=>'marker.name',
			'required'=>true,
			'empty_data'=>'(unnamed)',
			'attr' => array('size'=>20, 'maxlength'=>60)
		));

		$builder->add('type', 'choice', array(
			'label'=>'marker.type',
			'required'=>true,
			'choices'=>array('waypoint'=>'marker.waypoint', 'enemy'=>'marker.enemy')
		));

		$realms = array();
		foreach ($this->realms as $realm) {
			$realms[] = $realm->getId();
		}
		$builder->add('realm', 'entity', array(
			'label'=>'marker.realm',
			'required'=>true,
			'class'=>'BM2SiteBundle:Realm', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($realms) {
				$qb = $er->createQueryBuilder('r');
				$qb->where('r IN (:realms)');
				$qb->setParameter('realms', $realms);
				return $qb;
			},
		));

		// this is "new" because of the JS dependency which is "new" because of the form field in feature construction
		$builder->add('new_location_x', 'hidden', array('required'=>false));
		$builder->add('new_location_y', 'hidden', array('required'=>false));

		$builder->add('submit', 'submit', array('label'=>'marker.submit'));

	}

}
