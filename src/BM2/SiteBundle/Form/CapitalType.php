<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

class CapitalType extends AbstractType {

	private $realm;

	public function __construct($realm) {
		$this->realm = $realm;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'capital_931',
			'translation_domain' => 'politics',
			'attr'					=> array('class'=>'wide')
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$realm = $this->realm;

		$builder->add('capital', 'entity', array(
			'label' => 'realm.capital.estates',
			'multiple'=>false,
			'expanded'=>false,
			'class'=>'BM2SiteBundle:Settlement', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($realm) {
				$qb = $er->createQueryBuilder('e');
				$qb->where('e.realm = :realm')->setParameter('realm', $realm);
				$qb->orderBy('e.name');
				return $qb;
			},
		));

		$builder->add('submit', 'submit', array('label'=>'realm.capital.submit'));
	}

	public function getName() {
		return 'capital';
	}
}
