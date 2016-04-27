<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Settlement;


class TradeType extends AbstractType {

	private $character;
	private $settlement;
	private $sources;

	public function __construct(Character $character, Settlement $settlement, $sources) {
		$this->character = $character;
		$this->settlement = $settlement;
		$this->sources = $sources;
	}

	public function getName() {
		return 'trade';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'trade_5710',
			'data_class'		=> 'BM2\SiteBundle\Entity\Trade',
		));
	}


	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('name', 'text', array(
			'label'=>'tradename',
			'required'=>false,
			'attr' => array('size'=>20, 'maxlength'=>40)
		));

		$builder->add('amount', 'integer', array(
			'attr' => array('size'=>3)
		));

		$builder->add('resourcetype', 'entity', array(
			'label' => 'resource',
			'required'=>true,
			'placeholder' => 'form.choose',
			'choice_translation_domain' => true,
			'class'=>'BM2SiteBundle:ResourceType',
			'choice_label'=>'name'
		));

		// you can always send FROM any of your estates
		// FIXME: to get trade permissions working, this and the code in ActionsController:tradeAction need to be refactored to include permissions
		//			 also, maybe this should use $sources instead of the owner comparison?
		$character = $this->character;
		$builder->add('source', 'entity', array(
			'label' => 'source',
			'placeholder' => ($character->getEstates()->count()>1?'form.choose':false),
			'class'=>'BM2SiteBundle:Settlement', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($character) {
				$qb = $er->createQueryBuilder('s');
				$qb->where('s.owner = :me');
				$qb->orderBy('s.name');
				$qb->setParameter('me', $character);
				return $qb;
			},
		));

		// you can send TO this place if it's not yours, or to any of your estates if it is
		// however, there's one additional validation that either source or target must be your current location

		// TODO: you should also be able to send to the estates of other characters who are nearby
		
		if ($this->settlement->getOwner() == $this->character) {
			$sources = $this->sources;
			$builder->add('destination', 'entity', array(
				'label' => 'destination',
				'placeholder' => (count($sources)>1?'form.choose':false),
				'class'=>'BM2SiteBundle:Settlement', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($sources) {
					$qb = $er->createQueryBuilder('s');
					$qb->where('s.id in (:sources)');
					$qb->orderBy('s.name');
					$qb->setParameter('sources', $sources);
					return $qb;
				},
			));
		} else {
			$settlement = $this->settlement;
			$builder->add('destination', 'entity', array(
				'label' => 'destination',
				'placeholder' => false,
				'class'=>'BM2SiteBundle:Settlement', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($settlement) {
					$qb = $er->createQueryBuilder('s');
					$qb->where('s = :here');
					$qb->orderBy('s.name');
					$qb->setParameter('here', $settlement);
					return $qb;
				},
			));
		}

	}


}
