<?php

namespace BM2\SiteBundle\Form;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\Siege;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class SiegeType extends AbstractType {

	public function __construct(Character $character, Settlement $settlement, Siege $siege, $action = null) {
		$this->character = $character;
		$this->settlement = $settlement;
		$this->siege = $siege;
		$this->action = $action;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'csrf_token_id'       => 'siege_97',
			'translation_domain' => 'actions',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$siege = $this->siege;
		$settlement = $this->settlement;
		$character = $this->character;
		$action = $this->action;
		$isLeader = FALSE;
		$defLeader = FALSE;
		$attLeader = FALSE;
		$actionslist = array();
#$actionslist = array('leadership' => 'siege.action.leadership', 'assault' => 'siege.action.assault', 'disband' => 'siege.action.disband', 'join' => 'siege.action.join');
		#NOTE: $allactions = array('leadership', 'build', 'assault', 'disband', 'leave', 'attack', 'join', 'assume');
		# Figure out if we're the group leader, and while we're at it, if both groups have leaders.
		if (!$action || $action == 'select') {
			foreach ($siege->getGroups() as $group) {
				if ($character == $group->getLeader()) {
					
					$isLeader = TRUE;
					if ($group->isAttacker() && $group->getLeader()) {
						$attLeader = TRUE;
						# Not used now, but later we'll set this up so other people can assume leadership of attackers in certain instances.
					}
					if ($group->isDefender() && $group->getLeader()) {
						$defLeader = TRUE;
						# If this isn't TRUE, the lord can assume leadership of the siege.
					}
				}
			}
			$actionslist = array('attack' => 'siege.action.attack');
			# $actionslist = array('build' => 'siege.action.build', 'attack' => 'siege.action.attack'); #For when we implement siege equipment.
			if ($isLeader) {
				$actionslist = array_merge($actionslist, array('disband'=>'siege.action.disband', 'leadership'=>'siege.action.leadership', 'assault'=>'siege.action.assault'));
			} else {
				$actionslist = array_merge($actionslist, array('leave' => 'siege.action.leave'));
			}
			if ($character->getInsideSettlement() == $settlement && $settlement->getOwner() == $character && !$defLeader) {
				$actionslist = array_merge($actionslist, array('assume'=>'siege.action.assume'));
			}
			if (!$siege->getBattles()->isEmpty()) {
				$actionslist = array_merge($actionslist, array('join'=>'siege.action.join'));
			}
			ksort($actionslist, 2); #Sort array as strings.
			$builder->add('action', ChoiceType::class, array(
				'required'=>true,
				'choices' => $actionslist,
				'label'=> 'siege.actions',
			));
		} else {
			$builder->add('action', HiddenType::class, array(
				'data'=>'selected'
			));
			switch($action) {
				case 'leadership':
					$builder->add('subaction', HiddenType::class, array(
						'data'=>'leadership'
					));
					$builder->add('newleader', 'entity', array(
						'label'=>'siege.newleader',
						'required'=>true,
						'placeholder'=>'character.none',
						'attr'=>array('title'=>'siege.help.newleader'),
						'class'=>'BM2SiteBundle:Character',
						'choice_label'=>'name',
						'query_builder'=>function(EntityRepository $er) use ($character, $siege) {
							return $er->createQueryBuilder('c')->leftjoin('c.battlegroups', 'bg')->where(':character = bg.leader')->andWhere('bg.siege = :siege')->setParameters(array('character'=>$character, 'siege'=>$siege))->orderBy('c.name', 'ASC');
						}
					));
					break;
				case 'build':
					$builder->add('subaction', HiddenType::class, array(
						'data'=>'build'
					));
					$builder->add('quantity', 'integer', array(
						'attr'=>array('size'=>3)
					));
					/*
					$form->add('type', 'entity', array(
						'label'=>'siege.newequpment',
						'required'=>true,
						'placeholder'=>'equipment.none'
						'attr'=>array('title'=>'siege.help.equipmenttype'),
						'class'=>'BM2SiteBundle:SiegeEquipmentType',
						'choice_label'=>'nameTrans'
						'query_builder'=>function(EntityRepository $er){
							return $er->createQueryBuilder('e')->orderBy('e.name', 'ASC');
						}
					));
					*/
					break;
				case 'assault':
					$builder->add('subaction', HiddenType::class, array(
						'data'=>'assault'
					));
					$builder->add('assault', CheckboxType::class, array(
						'label' => 'siege.assault',
						'required' => true
					));
					break;
				case 'disband':
					$builder->add('subaction', HiddenType::class, array(
						'data'=>'disband'
					));
					$builder->add('disband', CheckboxType::class, array(
						'label' => 'siege.disband',
						'required' => true
					));
					break;
				case 'leave':
					$builder->add('subaction', HiddenType::class, array(
						'data'=>'leave'
					));
					$builder->add('leave', CheckboxType::class, array(
						'label' => 'siege.leave',
						'required' => true
					));
					break;
				case 'attack':
					$builder->add('subaction', HiddenType::class, array(
						'data'=>'attack'
					));
					$builder->add('attack', CheckboxType::class, array(
						'label' => 'siege.attack',
						'required' => true
					));
					break;
				case 'join':
					$builder->add('subaction', HiddenType::class, array(
						'data'=>'join'
					));
					$builder->add('join', CheckboxType::class, array(
						'label' => 'siege.join',
						'required' => true
					));
					break;
				case 'assume':
					$builder->add('subaction', HiddenType::class, array(
						'data'=>'assume'
					));
					$builder->add('assume', CheckboxType::class, array(
						'label' => 'siege.assume',
						'required' => true
					));
					break;
			}
		};

		$builder->add('submit', SubmitType::class, array('label'=>'siege.submit'));

	}

	public function getName() {
		return 'siege';
	}
}
