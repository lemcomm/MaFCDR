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

}
