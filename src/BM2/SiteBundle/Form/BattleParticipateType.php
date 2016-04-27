<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class BattleParticipateType extends AbstractType {

	private $battles;

	public function __construct($battles) {
		$this->battles = $battles;
	}

	public function getName() {
		return 'battleparticipate';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'battleparticipate_5106',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$battles = $this->battles;

		$builder->add('group', 'entity', array(
			'label'=>'battlegroup',
			'placeholder'=>'form.choose',
			'class'=>'BM2SiteBundle:BattleGroup', 'choice_label'=>'id', 'query_builder'=>function(EntityRepository $er) use ($battles) {
				$qb = $er->createQueryBuilder('g')->innerJoin('g.battle', 'b');
				$qb->where('b IN (:battles)');
				$qb->setParameter('battles', $battles);
				return $qb;
		}));

	}

}
