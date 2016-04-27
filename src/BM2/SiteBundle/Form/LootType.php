<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityManager;

use BM2\SiteBundle\Entity\Settlement;


class LootType extends AbstractType {

	private $settlement;
	private $em;
	private $inside;
	private $npc;

	public function __construct(Settlement $settlement, EntityManager $em, $inside, $npc) {
		$this->settlement = $settlement;
		$this->em = $em;
		$this->inside = $inside;
		$this->npc = $npc;
	}


	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'loot_1541',
			'translation_domain' => 'actions',
			'attr'					=> array('class'=>'wide')
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		if ($this->inside) {
			$choices = array(
				'thralls'	=> 'military.settlement.loot.option.thralls',
				'supply'		=> 'military.settlement.loot.option.supply',
				'resources'	=> 'military.settlement.loot.option.resources',
				'wealth'		=> 'military.settlement.loot.option.wealth',
				'burn'		=> 'military.settlement.loot.option.burn',
			);
		} else {
			$choices = array(
				'thralls'	=> 'military.settlement.loot.option.thralls',
				'supply'		=> 'military.settlement.loot.option.food',
				'resources'	=> 'military.settlement.loot.option.resources',
				'wealth'		=> 'military.settlement.loot.option.wealth',
			);
		}
		if ($this->npc) {
			// bandits cannot loot for thralls or resources (abuse potential)
			// FIXME: better would be if for bandits the destination settlement were a random nearby one
			unset($choices['thralls']);
			unset($choices['resources']);
		}
		$builder->add('method', 'choice', array(
			'label'=>'military.settlement.loot.options',
			'multiple'=>true,
			'expanded'=>true,
			'choice_translation_domain' => true,
			'choices'=>$choices 
		));

		$settlement_transformer = new SettlementTransformer($this->em);
		$builder->add(
			$builder->create('target', 'text', array(
			'label'=>'military.settlement.loot.target',
			'required' => false,
			'attr'=>array('class'=>'settlementselect'),
			))->addModelTransformer($settlement_transformer)
		);

		$builder->add('submit', 'submit', array('label'=>'military.settlement.loot.submit'));
	}

	public function getName() {
		return 'loot';
	}
}
