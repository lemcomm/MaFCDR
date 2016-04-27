<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

class SubrealmType extends AbstractType {

	private $realm;

	public function __construct($realm) {
		$this->realm = $realm;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'estates_824',
			'translation_domain' => 'politics',
			'attr'					=> array('class'=>'wide')
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$realm = $this->realm;

		$builder->add('estate', 'entity', array(
			'label' => 'diplomacy.subrealm.estates',
			'multiple'=>true,
			'expanded'=>true,
			'class'=>'BM2SiteBundle:Settlement', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($realm) {
				$qb = $er->createQueryBuilder('e');
				$qb->where('e.realm = :realm')->setParameter('realm', $realm);
				$qb->orderBy('e.name');
				return $qb;
			},
		));

		$builder->add('name', 'text', array(
			'label'=>'realm.name',
			'required'=>true,
			'attr' => array('size'=>20, 'maxlength'=>40)
		));
		$builder->add('formal_name', 'text', array(
			'label'=>'realm.formalname',
			'required'=>true,
			'attr' => array('size'=>40, 'maxlength'=>160)
		));

		$realmtypes = array();
		for ($i=1;$i<$this->realm->getType();$i++) {
			$realmtypes[$i] = 'realm.type.'.$i;
		}

		$builder->add('type', 'choice', array(
			'required'=>true,
			'placeholder'=>'diplomacy.subrealm.empty',
			'choices' => $realmtypes,
			'label'=> 'realm.designation',
		));


		$builder->add('ruler', 'entity', array(
			'label' => 'diplomacy.subrealm.ruler',
			'placeholder'=>'diplomacy.subrealm.empty',
			'multiple'=>false,
			'expanded'=>false,
			'class'=>'BM2SiteBundle:Character', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($realm) {
				$qb = $er->createQueryBuilder('c');
				$qb->join('c.estates', 'e');
				$qb->where('e.realm = :realm')->setParameter('realm', $realm);
				$qb->orderBy('c.name');
				return $qb;
			},
		));


		$builder->add('submit', 'submit', array('label'=>'diplomacy.subrealm.submit'));
	}

	public function getName() {
		return 'estates';
	}
}
