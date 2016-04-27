<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

class TradeCancelType extends AbstractType {

	private $trades;
	private $character;

	public function __construct($trades, $character) {
		$this->trades = $trades;
		$this->character = $character;
	}

	public function getName() {
		return 'tradecancel';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'tradecancel_255',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {

		$trades = $this->trades;
		$character = $this->character;
		if ($trades) {
			$builder->add('trade', 'entity', array(
				'class'=>'BM2SiteBundle:Trade', 'choice_label'=>'id', 'query_builder'=>function(EntityRepository $er) use ($trades, $character) {
					$qb = $er->createQueryBuilder('r');
					$qb->where('r IN (:trades)');
					$qb->setParameter('trades', $trades);
					return $qb;
				},
			));
		} else {
			$builder->add('trade', 'choice', array(
					'choices' => array(0),
				)
			);
		}
	}


}
