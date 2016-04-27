<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class InteractionType extends AbstractType {

	private $action;
	private $maxdistance;
	private $me;
	private $multiple;
	private $settlementcheck;

	public function __construct($action, $maxdistance, $me, $multiple=false, $settlementcheck=false) {
		$this->action = $action;
		$this->maxdistance = $maxdistance;
		$this->me = $me;
		$this->multiple = $multiple;
		$this->settlementcheck = $settlementcheck;
	}

	public function getName() {
		return 'interaction';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'interaction_12331',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$me = $this->me;
		$maxdistance = $this->maxdistance;
		$settlementcheck = $this->settlementcheck;

		$builder->add('target', 'entity', array(
			'label'=>'interaction.'.$this->action.'.name',
			'placeholder'=>$this->multiple?'character.none':null,
			'multiple'=>$this->multiple,
			'expanded'=>true,
			'class'=>'BM2SiteBundle:Character', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($me, $maxdistance, $settlementcheck) {
				$qb = $er->createQueryBuilder('c');
				$qb->from('BM2SiteBundle:Character', 'me');
				$qb->where('c.alive = true');
				$qb->andWhere('c.prisoner_of IS NULL');
				$qb->andWhere('me = :me')->andWhere('c != me')->setParameter('me', $me);
				if ($maxdistance) {
					$qb->andWhere('ST_Distance(me.location, c.location) < :maxdistance')->setParameter('maxdistance', $maxdistance);					
				}
				if ($settlementcheck) {
					if (!$me->getInsideSettlement()) {
						// if I am not inside a settlement, I can only attack others who are outside as well
						$qb->andWhere('c.inside_settlement IS NULL');
					}
				}
				return $qb;
		}));

		$method = $this->action."Fields";
		if (method_exists(__CLASS__, $method)) {
			$this->$method($builder, $options);
		}

		$builder->add('submit', 'submit', array('label'=>'interaction.'.$this->action.'.submit'));
	}

	private function attackFields(FormBuilderInterface $builder, array $options) {
		$builder->add('message', 'textarea', array(
			'label' => 'interaction.attack.message',
			'required' => true,
			'empty_data' => '(no message)'
		));
	}


	private function messageFields(FormBuilderInterface $builder, array $options) {
		$builder->add('subject', 'text', array(
			'label' => 'interaction.message.subject',
			'required' => true
		));
		$builder->add('body', 'textarea', array(
			'label' => 'interaction.message.body',
			'required' => true,
			'empty_data' => '(no message)'
		));
	}

	private function grantFields(FormBuilderInterface $builder, array $options) {
		$builder->add('withrealm', 'checkbox', array(
			'required' => false,
			'label' => 'control.grant.withrealm',
			'attr' => array('title'=>'control.grant.withrealm2'),
			'translation_domain' => 'actions'
		));
		$builder->add('keepclaim', 'checkbox', array(
			'required' => false,
			'label' => 'control.grant.keepclaim',
			'attr' => array('title'=>'control.grant.keeprealm2'),
			'translation_domain' => 'actions'
		));
	}

	private function givegoldFields(FormBuilderInterface $builder, array $options) {
		$builder->add('amount', 'integer', array(
			'required' => true,
			'label' => 'interaction.givegold.amount',
		));
	}

	private function giveartifactFields(FormBuilderInterface $builder, array $options) {
		$me = $this->me;
		$builder->add('artifact', 'entity', array(
			'required' => true,
			'label' => 'interaction.giveartifact.which',
			'class'=>'BM2SiteBundle:Artifact',
			'property'=>'name',
			'query_builder'=>function(EntityRepository $er) use ($me) {
				return $er->createQueryBuilder('a')->where('a.owner = :me')->setParameter('me', $me);
			}
		));
	}

}
