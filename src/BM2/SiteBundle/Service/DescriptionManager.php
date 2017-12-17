<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Achievement;
use BM2\SiteBundle\Entity\Artifact;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Description;
use BM2\SiteBundle\Entity\Item;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\User;
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
	
	#TODO: Move this getClassName method, and similar get_class_name methods, into a single HelperService file.
	private function getClassName($entity) {
		$classname = get_class($entity);
		if ($pos = strrpos($classname, '\\')) return substr($classname, $pos + 1);
		return $pos;
	}
	
	public function newDescription($entity, $text, Character $character=null) {
		// First, check to see if there's already one.
		$olddesc = $this->findDescription($entity)

		$desc = new Description();
		$this->em->persist($desc);
		$entity->setDescription($desc);
		if ($olddesc) {
			$desc->setPrevious($olddesc);
		}
		$desc->setText($text);
		$desc->setTs(new \DateTime("now"));
		$desc->setCycle($this->appstate->getCycle());
		$this->em->flush($desc);
		switch($this->getClassName($entity)) {
			case 'Artifact':
				$this->history->logEvent(
					$entity,
					'event.description.updated.artifact',
					null,
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
					array('%character%'=>$character->getId(), %place%=>$entity->getId()),
					History::LOW
				);
				break;
			case 'Settlement':
				$this->history->logEvent(
					$entity,
					'event.description.updated.settlement',
					array('%character%'=>$entity->getOwner()->getId(), %settlement%=>$entity->getId()),
					History::LOW
				);
				break;
		}
		return $desc;
	}
	
	public function findDescription($entity) {
		if ($entity->getDescription()) {
			return $entity->getDescription();
		}
		switch($this->getClassName($entity)) {
			case 'Artifact':
				$query = $this->em->createQuery("select d from BM2SiteBundle:Description d where d.artifact = :entity order by ts desc");
				break;
			case 'Item':
				$query = $this->em->createQuery("select d from BM2SiteBundle:Description d where d.item = :entity order by ts desc");
				break;
			case 'Place':
				$query = $this->em->createQuery("select d from BM2SiteBundle:Description d where d.place = :entity order by ts desc");
				break;
			case 'Settlement':
				$query = $this->em->createQuery("select d from BM2SiteBundle:Description d where d.settlement = :entity order by ts desc");
				break;
			default:
				break;
		}
		$query->setParameter('entity', $entity);
		$query->setMaxResults(1);
		$desc = $query->getOneOrNullResult();
		if (!$desc) {
			return null;
		} else {
			return $desc;
		}
	}
