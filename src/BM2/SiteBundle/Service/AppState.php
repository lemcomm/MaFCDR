<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Setting;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\Security\Core\User\UserInterface;


class AppState {

	protected $em;
	protected $securitycontext;
	protected $session;

	private $languages = array(
		'en' => 'english',
		'de' => 'deutsch',
		'es' => 'español',
		'fr' => 'français',
		'it' => 'italiano'
		);

	public function __construct(EntityManager $em, SecurityContext $securitycontext, Session $session) {
		$this->em = $em;
		$this->securitycontext = $securitycontext;
		$this->session = $session;
	}

	public function availableTranslations() {
		return $this->languages;
	}

	public function getCharacter($required=true, $ok_if_dead=false, $ok_if_notstarted=false) {
		// check if we have a user first
		$token = $this->securitycontext->getToken();
		if (!$token) {
			if (!$required) { return null; } else { throw new AccessDeniedException('error.missing.token'); }
		}
		$user = $token->getUser();
		if (!$user || ! $user instanceof UserInterface) {
			if (!$required) { return null; } else { throw new AccessDeniedException('error.missing.user'); }
		}

		# Let the ban checks begin...
		if ($this->securitycontext->isGranted('ROLE_BANNED_MULTI')) {
			if (!$required) { return null; } else { throw new AccessDeniedException('error.banned.multi'); }
		}

		// FIXME: these also redirect to the login page, which is bullshit, they should redirect to the characters page

		$character = $user->getCurrentCharacter();
		if (!$character) {
			if (!$required) { return null; } else { throw new AccessDeniedException('error.missing.character'); }
		}
		if (!$ok_if_dead && !$character->isAlive()) {
			// TODO: redirect to obituary page (which should allow reading of logs, etc, until the time of death)
			if (!$required) { return null; } else { throw new AccessDeniedException('error.missing.soul'); }
		}
		if (!$ok_if_notstarted && !$character->getLocation()) {
			if (!$required) { return null; } else { throw new AccessDeniedException('error.missing.location'); }
		}

		if ($character->isAlive()) {
			$character->setLastAccess(new \DateTime('now')); // no flush here, most actions will issue one anyways and we don't need 100% reliability
		}
		return $character;
	}

	public function getDate($cycle=null) {
		// our in-game date - 6 days a week, 60 weeks a year = 1 year about 2 months
		if (null===$cycle) {
			$cycle = $this->getCycle();
		}

		$year = floor($cycle/360)+1;
		$week = floor($cycle%360/6)+1;
		$day = ($cycle%6)+1;
		return array('year'=>$year, 'week'=>$week, 'day'=>$day);
	}

	public function getCycle() {
		return (int)($this->getGlobal('cycle', 0));
	}

	public function getGlobal($name, $default=false) {
		$setting = $this->em->getRepository('BM2SiteBundle:Setting')->findOneByName($name);
		if (!$setting) return $default;
		return $setting->getValue();
	}
	public function setGlobal($name, $value) {
		$setting = $this->em->getRepository('BM2SiteBundle:Setting')->findOneByName($name);
		if (!$setting) {
			$setting = new Setting();
			$setting->setName($name);
			$this->em->persist($setting);
		}
		$setting->setValue($value);
		$this->em->flush($setting);
	}


	public function setSessionData(Character $character) {
		$this->session->clear();
		if ($character->isAlive()) {
			if ($character->getInsideSettlement()) {
				$this->session->set('nearest_settlement', $character->getInsideSettlement());
			} elseif ($character->getLocation()) {
				$near = $this->findNearestSettlement($character);
				$this->session->set('nearest_settlement', $near[0]);
			}
			$this->session->set('soldiers', $character->getLivingSoldiers()->count());
			$this->session->set('entourage', $character->getLivingEntourage()->count());
			$query = $this->em->createQuery('SELECT s.id, s.name FROM BM2SiteBundle:Settlement s WHERE s.owner = :me');
			$query->setParameter('me', $character);
			$estates = array();
			foreach ($query->getResult() as $row) {
				$estates[$row['id']] = $row['name'];
			}
			$this->session->set('estates', $estates);
			$realms = array();
			foreach ($character->findRulerships() as $realm) {
				$realms[$realm->getId()] = $realm->getName();
			}
			$this->session->set('realms', $realms);
		}
	}

	// FIXME: this is duplicate code from Geography.php but I can't inject the geography service because it would create a circular injection (as it depends on appstate)
	private function findNearestSettlement(Character $character) {
		$query = $this->em->createQuery('SELECT s, ST_Distance(g.center, c.location) AS distance FROM BM2SiteBundle:Settlement s JOIN s.geo_data g, BM2SiteBundle:Character c WHERE c = :char ORDER BY distance ASC');
		$query->setParameter('char', $character);
		$query->setMaxResults(1);
		return $query->getSingleResult();
	}


}
