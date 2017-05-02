<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class NpcSelectType extends AbstractType {

	private $characters;

	public function __construct($characters) {
		$this->characters = $characters;
	}

	public function getName() {
		return 'npcselect';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'npc_select_5214'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$characters = $this->characters;

		$builder->add('npc', 'entity', array(
			'placeholder' => 'form.choose',
			'label' => 'bandits.choose',
			'required' => true,
			'class'=>'BM2SiteBundle:Character', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($characters) {
				$qb = $er->createQueryBuilder('c');
				$qb->where('c IN (:characters) AND c.alive = TRUE');
				$qb->setParameter('characters', $characters);
				return $qb;
			},
		));

		$builder->add('submit', 'submit', array('label'=>'bandits.submit'));
	}


}
