<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\NewsArticle;
use BM2\SiteBundle\Entity\NewsEdition;
use BM2\SiteBundle\Entity\NewsEditor;
use BM2\SiteBundle\Entity\NewsPaper;
use BM2\SiteBundle\Entity\NewsReader;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;


class NewsManager {

	protected $em;
	protected $appstate;
	protected $geography;

	public function __construct(EntityManager $em, AppState $appstate, Geography $geography) {
		$this->em = $em;
		$this->appstate = $appstate;
		$this->geography = $geography;
	}


	public function newPaper($name, Character $creator) {
		$paper = new NewsPaper;
		$paper->setName($name);
		$paper->setCreatedAt(new \DateTime("now"));
		$paper->setSubscription(false);
		$this->em->persist($paper);

		$this->addEditor($paper, $creator, true, true, true, true);

		$collection = $this->newEdition($paper);
		$collection->setCollection(true);

		return $paper;
	}

	public function newEdition(NewsPaper $paper) {
		$edition = new NewsEdition;
		$edition->setNumber($paper->getEditions()->count());
		$edition->setCollection(false);
		$edition->setPublishedCycle(null);
		$edition->setPublished(null);
		$edition->setPaper($paper);
		$this->em->persist($edition);

		return $edition;
	}

	public function publishEdition(NewsEdition $edition) {
		$edition->setPublished(new \DateTime("now"));
		$edition->setPublishedCycle($this->appstate->getCycle());

		// TODO: notifications or whatever?

	}

	public function addEditor(NewsPaper $paper, Character $character, $is_publisher=true, $is_author=false, $is_editor=false, $is_owner=false) {
		$editor = new NewsEditor;
		$editor->setPublisher($is_publisher);
		$editor->setAuthor($is_author);
		$editor->setEditor($is_editor);
		$editor->setOwner($is_owner);

		$editor->setPaper($paper);
		$editor->setCharacter($character);

		$this->em->persist($editor);
	}

	public function addArticle(NewsArticle $article) {
		$article->setPosition($article->getEdition()->getArticles()->count());
		$len = strlen($article->getContent());
		$article->setRow(1)->setCol(1);
		if ($len<200) {
			$article->setSizeX(1)->setSizeY(1);
		} elseif ($len<400) {
			$article->setSizeX(1)->setSizeY(2);
		} elseif ($len<600) {
			$article->setSizeX(1)->setSizeY(3);
		} elseif ($len<800) {
			$article->setSizeX(2)->setSizeY(2);
		} elseif ($len<1200) {
			$article->setSizeX(2)->setSizeY(3);
		} elseif ($len<1600) {
			$article->setSizeX(2)->setSizeY(4);
		} elseif ($len<2000) {
			$article->setSizeX(3)->setSizeY(3);
		} elseif ($len<2500) {
			$article->setSizeX(3)->setSizeY(4);
		} else {
			$article->setSizeX(4)->setSizeY(4);			
		}

		$this->em->persist($article);
	}

	public function accessPaper(NewsPaper $paper, Character $character) {
		$result = $this->em->getRepository('BM2SiteBundle:NewsEditor')->findBy(array('paper'=>$paper, 'character'=>$character));
		if (!$result || empty($result)) return false;
		return $result[0];
	}

	public function readEdition(NewsEdition $edition, Character $character) {
		$result = $this->em->getRepository('BM2SiteBundle:NewsReader')->findBy(array('edition'=>$edition, 'character'=>$character));
		if (!$result || empty($result)) {
			// check if it is locally published
			if (! $character->getTravelAtSea()) {
				$nearest = $this->geography->findNearestSettlement($character);
				$settlement=array_shift($nearest);
				if ($settlement->getOwner()) {
					foreach($settlement->getOwner()->getNewspapersEditor() as $editor) {
						if ($editor->getPublisher() && $editor->getPaper() == $edition->getPaper()) {
							return true;
						}
					}
				}
			}
			return false;
		}
		return $result[0];
	}

	public function latestEdition(NewsPaper $paper) {
		$query = $this->em->createQuery('SELECT e FROM BM2SiteBundle:NewsEdition e WHERE e.paper = :paper AND e.published IS NOT NULL ORDER BY e.number DESC');
		$query->setParameters(array('paper'=>$paper));
		$query->setMaxResults(1);
		return $query->getOneOrNullResult();
	}


	public function canCreatePaper(Character $character) {
		// we need to be lord of at least one estate with a library
		$library = $this->em->getRepository('BM2SiteBundle:BuildingType')->findOneByName('Library');
		foreach ($character->getOwnedSettlements() as $settlement) {
			if ($settlement->hasBuilding($library)) {
				return true;
			}
		}
		return false;
	}

	public function getLocalList(Character $character) {
		$local = new ArrayCollection;
		if (! $character->getTravelAtSea()) {
			$nearest = $this->geography->findNearestSettlement($character);
			$settlement=array_shift($nearest);
			if ($settlement->getOwner()) {
				foreach($settlement->getOwner()->getNewspapersEditor() as $editor) {
					if ($editor->getPublisher()) {
						if ($latest = $this->latestEdition($editor->getPaper())) {
							$local->add($latest);
						}
					}
				}
			}
		}
		return $local;
	}
}
