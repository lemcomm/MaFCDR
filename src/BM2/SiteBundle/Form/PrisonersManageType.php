<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class PrisonersManageType extends AbstractType {

	private $prisoners;
	private $others;

	public function __construct($prisoners, $others) {
		$this->prisoners = $prisoners;
		$this->others = $others;
	}

	public function getName() {
		return 'prisonersmanage';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'prisonersmanage_91356',
			'translation_domain' => 'politics'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('prisoners', 'form');

		foreach ($this->prisoners as $prisoner) {
			$actions = array(
				'free' => 'diplomacy.prisoners.free',
				'execute' => 'diplomacy.prisoners.execute'
			);
			if (!empty($this->others) && !$prisoner->hasAction('personal.prisonassign')) {
				$actions['assign'] = 'diplomacy.prisoners.assign';
			}

			$idstring = (string)$prisoner->getId();
			$builder->get('prisoners')->add($idstring, 'form', array('label'=>$prisoner->getName()));
			$field = $builder->get('prisoners')->get($idstring);

			$field->add('action', 'choice', array(
				'choices' => $actions,
				'required' => false,
				'choice_translation_domain' => true,
				'attr' => array('class'=>'action')
			));
			$field->add('method', 'choice', array(
				'choices' => array(
					'behead'	=> 'diplomacy.prisoners.kill.behead',
					'hang' => 'diplomacy.prisoners.kill.hang',
					'burn' => 'diplomacy.prisoners.kill.burn',
					'quarter' => 'diplomacy.prisoners.kill.quarter',
					'impale' => 'diplomacy.prisoners.kill.impale'
				),
				'choice_translation_domain' => true,
				'required' => false,
				'placeholder' => 'diplomacy.prisoners.choose',
				'attr' => array('class'=>'method')
			));
		}

		if (!empty($this->others)) {
			$others = $this->others;
			$builder->add('assignto', 'entity', array(
				'placeholder' => 'diplomacy.prisoners.choose',
				'label' => 'diplomacy.prisoners.assign',
				'required' => false,
				'class'=>'BM2SiteBundle:Character', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($others) {
					$qb = $er->createQueryBuilder('c');
					$qb->where('c IN (:others)');
					$qb->setParameter('others', $others);
					return $qb;
				},
			));			
		}

	}

}
