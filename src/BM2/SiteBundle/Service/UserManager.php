<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\User;
use Doctrine\Common\Persistence\ObjectManager;
use FOS\UserBundle\Doctrine\UserManager as FosUserManager;
use FOS\UserBundle\Util\CanonicalFieldsUpdater;
use FOS\UserBundle\Util\PasswordUpdaterInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;


class UserManager extends FosUserManager {
	private $genome_all = 'abcdefghijklmnopqrstuvwxyz';
	private $genome_setsize = 15;


	/**
	 * @param ObjectManager $om
	 */
	public function __construct(ObjectManager $om, $class, PasswordUpdaterInterface $passwordUpdater, CanonicalFieldsUpdater $canonicalFieldsUpdater) {
		parent::__construct($passwordUpdater, $canonicalFieldsUpdater, $om, $class);
	}

	/**
	 * {@inheritdoc}
	 */
	public function refreshUser( UserInterface $user ) {
		return $this->findUserBy( array( 'id' => $user->getId() ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function supportsClass( $class ) {
		return $class instanceof User;
	}

	/**
	 * Creates an empty user instance.
	 *
	 * @return UserInterface
	 */
	public function createUser() {
		$user = new User;
		$user->setDisplayName("(anonymous)");
		$user->setCreated(new \DateTime("now"));
		$user->setNewCharsLimit(3);
		$user->setArtifactsLimit(0);
		$user->setNotifications(true);
		$user->setNewsletter(true);
		$user->setCredits(0);
		$user->setVipStatus(0);
		$user->setRestricted(false);
		// new users subscription is 30-days, as in the old trial, but mostly because our payment interval is monthly for them
		$until = new \DateTime("now");
		$until->add(new \DateInterval('P30D'));
		$user->setAccountLevel(10)->setPaidUntil($until);
		$user->setAppKey(sha1(time()."-maf-".mt_rand(0,1000000)));

		$user->setGenomeSet($this->createGenomeSet());

		return $user;
	}

	public function createGenomeSet() {
		$genome = str_split($this->genome_all);

		while (count($genome) > $this->genome_setsize) {
		    $pick = array_rand($genome);
		    unset($genome[$pick]);
		}

		return implode('', $genome);
	}

	public function calculateCharacterSpawnLimit(User $user, $refresh = false) {
		$newest = null;
		$count = 0;
		foreach ($user->getLivingCharacters() as $char) {
			if ($char->getLocation() && $char->getCreated() > $newest) {
				$newest = $char->getCreated();
			}
			$count++;
		}
		if ($count < 5) {
			$change = 0;
		} elseif (11 > $count && $count > 3) {
			$change = 3;
		} elseif (26 > $count && $count > 10) {
			$change = 7;
		} else {
			$change = 15;
		}
		$newest->modify('+'.$change.' days');
		if ($newest !== $user->getNextSpawnTime()) {
			$user->setNextSpawnTime($newest);
		}
		return $newest;
	}

	public function checkIfUserCanSpawnCharacters(User $user, $refresh = false) {
		$now = new \DateTime('now');
		if ($user->getNextSpawnTime() === null || $refresh) {
			$next = $this->calculateCharacterSpawnLimit($user, $refresh);
		} else {
			$next = $user->getNextSpawnTime();
		}
		if ($user->getLivingCharacters()->count() > 3 && $next > $now) {
			return false;
		} else {
			return true;
		}
	}

}
