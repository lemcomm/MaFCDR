<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class AssignedSoldiersType extends AbstractType {

	private $character;

	public function __construct($character) {
		$this->character = $character;
	}


	public function getName() {
		return 'assignedsoldiers';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'assignedsoldiers_514241',
			'translation_domain' => 'actions'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$character = $this->character;
		$builder->add('soldiers', 'entity', array(
			'label'=>'recruit.assigned.soldiers',
			'multiple'=>true,
			'expanded'=>true,
			'class'=>'BM2SiteBundle:Soldier', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($character) {
				return $er->createQueryBuilder('s')->where('s.liege = :liege')->orderBy('s.name')->setParameters(array('liege'=>$character));
		}));
	}

}
