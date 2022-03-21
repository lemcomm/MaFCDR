<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Artifact;
use BM2\SiteBundle\Entity\Association;
use BM2\SiteBundle\Entity\AssociationRank;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Deity;
use BM2\SiteBundle\Entity\Description;
use BM2\SiteBundle\Entity\Item;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\SpawnDescription;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;

class DescriptionManager {

	protected $em;
	protected $appstate;
	protected $history;

	public function __construct(EntityManager $em, AppState $appstate, History $history) {
		$this->em = $em;
		$this->appstate = $appstate;
		$this->history = $history;
	}

	#TODO: Move this getClassName method, and similar methods, into a single HelperService file.
	private function getClassName($entity) {
		$classname = get_class($entity);
		if ($pos = strrpos($classname, '\\')) return substr($classname, $pos + 1);
		return $pos;
	}

	public function newDescription($entity, $text, Character $character=null, $new=false) {
		/* First, check to see if there's already one. */
		$olddesc = NULL;
		if ($entity->getDescription()) {
			$olddesc = $entity->getDescription();
		}
		$eClass = $this->getClassName($entity);
		/* If we don't unset these and commit those changes, we create a unique key constraint violation when we commit the new ones. */
		if ($olddesc) {
			/* NOTE: If other things get descriptions, this needs updating with the new logic. */
			switch($eClass) {
				case 'Artifact':
					$olddesc->setActiveArtifact(NULL);
					$this->em->flush();
					break;
				case 'Association':
					$olddesc->setActiveAssociation(NULL);
					$this->em->flush();
					break;
				case 'AssociationRank':
					$olddesc->setActiveAssociationRank(NULL);
					$this->em->flush();
					break;
				case 'Deity':
					$olddesc->setActiveDeity(NULL);
					$this->em->flush();
					break;
				case 'House':
					$olddesc->setActiveHouse(NULL);
					$this->em->flush();
					break;
				case 'Item':
					$olddesc->setActiveItem(NULL);
					$this->em->flush();
					break;
				case 'Place':
					$olddesc->setActivePlace(NULL);
					$this->em->flush();
					break;
				case 'Realm':
					$olddesc->setActiveRealm(NULL);
					$this->em->flush();
					break;
				case 'Settlement':
					$olddesc->setActiveSettlement(NULL);
					$this->em->flush();
					break;
			}
		}

		$desc = new Description();
		$this->em->persist($desc);
		/* NOTE: If other things get descriptions, this needs updating with the new logic. */
		switch($eClass) {
			case 'Artifact':
				$desc->setActiveArtifact($entity);
				$desc->setArtifact($entity);
				break;
			case 'Association':
				$desc->setActiveAssociation($entity);
				$desc->setAssociation($entity);
				break;
			case 'AssociationRank':
				$desc->setActiveAssociationRank($entity);
				$desc->setAssociationRank($entity);
				break;
			case 'Deity':
				$desc->setActiveDeity($entity);
				$desc->setDeity($entity);
				break;
			case 'House':
				$desc->setActiveHouse($entity);
				$desc->setHouse($entity);
				break;
			case 'Item':
				$desc->setActiveItem($entity);
				$desc->setItem($entity);
				break;
			case 'Place':
				$desc->setActivePlace($entity);
				$desc->setPlace($entity);
				break;
			case 'Realm':
				$desc->setActiveRealm($entity);
				$desc->setRealm($entity);
				break;
			case 'Settlement':
				$desc->setActiveSettlement($entity);
				$desc->setSettlement($entity);
				break;
		}
		$entity->setDescription($desc);
		if ($olddesc) {
			$desc->setPrevious($olddesc);
		}
		$desc->setText($text);
		$desc->setUpdater($character);
		$desc->setTs(new \DateTime("now"));
		$desc->setCycle($this->appstate->getCycle());
		if (!$new) {
			/* No need to tell the people that just made the thing that they updated the descriptions. */
			/* Association Ranks deliberately ommitted. */
			switch($eClass) {
				case 'Artifact':
					$this->history->logEvent(
						$entity,
						'event.description.updated.artifact',
						null,
						History::LOW
					);
					break;
				case 'Association':
					$this->history->logEvent(
						$entity,
						'event.description.updated.assoc',
						null,
						History::LOW
					);
					break;
				case 'Deity':
					$this->history->logEvent(
						$entity,
						'event.description.updated.deity',
						null,
						History::LOW
					);
					break;
				case 'House':
					$this->history->logEvent(
						$entity,
						'event.description.updated.house',
						History::LOW
					);
					break;
				case 'Item':
					$this->history->logEvent(
						$entity,
						'event.description.updated.item',
						null,
						History::LOW
					);
					break;
				case 'Place':
					$this->history->logEvent(
						$entity,
						'event.description.updated.place',
						array('%link-character%'=>$character->getId(), '%link-place%'=>$entity->getId()),
						History::LOW
					);
					break;
				case 'Realm':
					$this->history->logEvent(
						$entity,
						'event.description.updated.realm',
						array('%link-character%'=>$character->getId()),
						History::LOW
					);
					break;
				case 'Settlement':
					$this->history->logEvent(
						$entity,
						'event.description.updated.settlement',
						array('%link-character%'=>$character->getId(), '%link-settlement%'=>$entity->getId()),
						History::LOW
					);
					break;
			}
		}
		$this->em->flush();
		return $desc;
	}

	public function newSpawnDescription($entity, $text, Character $character=null, $new=false) {
		/* First, check to see if there's already one. */
		$olddesc = NULL;
		if ($entity->getSpawnDescription()) {
			$olddesc = $entity->getSpawnDescription();
		}
		$eClass = $this->getClassName($entity);
		/* If we don't unset these and commit those changes, we create a unique key constraint violation when we commit the new ones. */
		if ($olddesc) {
			/* NOTE: If other things get descriptions, this needs updating with the new logic. */
			switch($eClass) {
				case 'House':
					$olddesc->setActiveHouse(null);
					break;
				case 'Place':
					$olddesc->setActivePlace(null);
					break;
				case 'Realm':
					$desc->setActiveRealm(null);
					break;
			}
			$this->em->flush();
		}

		$desc = new SpawnDescription();
		$this->em->persist($desc);
		/* NOTE: If other things get descriptions, this needs updating with the new logic. */
		switch($eClass) {
			case 'House':
				$desc->setActiveHouse($entity);
				$desc->setHouse($entity);
				break;
			case 'Place':
				$desc->setActivePlace($entity);
				$desc->setPlace($entity);
				break;
			case 'Realm':
				$desc->setActiveRealm($entity);
				$desc->setRealm($entity);
				break;
		}
		$entity->setSpawnDescription($desc);
		if ($olddesc) {
			$desc->setPrevious($olddesc);
		}
		$desc->setText($text);
		$desc->setUpdater($character);
		$desc->setTs(new \DateTime("now"));
		$desc->setCycle($this->appstate->getCycle());
		$this->em->flush($desc);
		return $desc;
	}
}
