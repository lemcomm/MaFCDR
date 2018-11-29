<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\BattleGroup;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Eneity\Siege;

use Doctrine\ORM\EntityManager;
use BM2\SiteBundle\Service\History;
use BM2\SiteBundle\Service\ActionResolution;

class WarManager {

	protected $em;
	protected $history;

	public function __construct(EntityManager $em, History $history, ActionResolution $resolver) {
		$this->em = $em;
		$this->history = $history;
		$this->resolver = $resolver;
	}

	public function disbandSiege(Siege $siege, Character $leader) {
		foreach ($siege->getGroups() as $group) {
			foreach ($group->getCharacters() as $character) {
				$this->history->logEvent(
					$character,
					'event.character.siege.disband',
					array('%link-settlement%'=>$siege->getSettlement()->getId(), '%link-character%'=>$leader->getId()),
					History::LOW, true
				);
				$this->addRegroupAction(null, $character);
			}
			$this->disbandGroup($group);
		}
		$this->em->flush();
		return true;
	}

	public function disbandGroup (BattleGroup $group) {
		foreach ($group->getCharacter() as $character) {
			$character->removeBattleGroup($group);
			$group->removeCharacter($character);
		}
		$this->em->remove($group);
		$this->em->flush();
	}

	public function addRegroupAction($battlesize=100, Character $character) {
		/* FIXME: to prevent abuse, this should be lower in very uneven battles
		FIXME: We should probably find some better logic about calculating the battlesize variable when this is called by sieges, but we can work that out later. */
		# setup regroup timer and change action
		$amount = min($this->battlesize*5, $character->getLivingSoldiers()->count())+2; # to prevent regroup taking long in very uneven battles
		$regroup_time = sqrt($amount*10) * 5; # in minutes

		$act = new Action;
		$act->setType('military.regroup')->setCharacter($character);
		$act->setBlockTravel(false);
		$act->setCanCancel(false);
		$complete = new \DateTime('now');
		$complete->add(new \DateInterval('PT'.ceil($regroup_time).'M'));
		$act->setComplete($complete);
		$this->resolver->queue($act, true);
	}
}

