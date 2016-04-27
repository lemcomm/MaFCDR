<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class EntourageManageType extends AbstractType {

	private $entourage;
	private $others;

	public function __construct($entourage, $others) {
		$this->entourage = $entourage;
		$this->others = $others;
	}

	public function getName() {
		return 'entouragemanage';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'entouragemanage_1456',
			'translation_domain' => 'actions'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('npcs', 'form');

		foreach ($this->entourage as $npc) {
			$idstring = (string)$npc->getId();
			$builder->get('npcs')->add($idstring, 'form', array('label'=>$npc->getName()));
			$field = $builder->get('npcs')->get($idstring);

			if ($npc->isLocked()) {

			} else {
				if ($npc->getAlive()) {
					$actions = array('disband2'=>'recruit.manage.disband');
					if (!empty($this->others)) {
						$actions['assign2'] = 'recruit.manage.assign';
					}
					if ($npc->getCharacter() && $npc->getCharacter()->isNPC()) {
						unset($actions['assign2']); // bandits cannot assign entourage
					}
				} else {
					$actions = array('bury' => 'recruit.manage.bury');
				}
				$field->add('action', 'choice', array(
					'choices' => $actions,
					'required' => false,
					'choice_translation_domain' => true,
					'attr' => array('class'=>'action')
				));
				if ($npc->getType()->getName()=="follower") {
					$field->add('supply', 'entity', array(
						'placeholder' => 'food',
						'required' => false,
						'class'=>'BM2SiteBundle:EquipmentType',
						'choice_label'=>'nameTrans',
						'query_builder'=>function(EntityRepository $er) {
							return $er->createQueryBuilder('e')->orderBy('e.name', 'ASC');
						},
						'data' => $npc->getEquipment(),
						'choice_translation_domain' => 'messages',
						'translation_domain' => 'messages'
					));
				}
			} // endif locked
		}

		if (!empty($this->others)) {
			$others = $this->others;
			$builder->add('assignto', 'entity', array(
				'placeholder' => 'form.choose',
				'label' => 'recruit.manage.assignto2',
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
