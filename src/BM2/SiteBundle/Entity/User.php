<?php

namespace BM2\SiteBundle\Entity;

use FOS\UserBundle\Model\User as BaseUser;
use Doctrine\Common\Collections\ArrayCollection;

class User extends BaseUser {

	protected $id;
	// the below are there to make Symphony 2 scripts happy that for some reason check the entity before the code generation is complete
	protected $display_name;
	protected $created;
	protected $new_chars_limit;
	protected $app_key;
	protected $language;
	protected $notifications;
	protected $newsletter;
	protected $account_level;
	protected $vip_status;
	protected $paid_until;
	protected $credits;
	protected $restricted;
	protected $current_character;
	protected $payments;
	protected $credit_history;
	protected $characters;
	protected $crests;
	protected $cultures;
	protected $ratings_given;
	protected $rating_votes;
	protected $listings;
	protected $genome_set;
	protected $artifacts;
	protected $artifacts_limit;

	public function __construct() {
		// NOTE: This bullshit crap shouldn't be necessary, but once again, Doctrine is too dumb to
		//		 include the parent::__construct() call itself on code generation.
		parent::__construct();
		$this->payments = new ArrayCollection();
		$this->credit_history = new ArrayCollection();
		$this->characters = new ArrayCollection();
		$this->crests = new ArrayCollection();
		$this->cultures = new ArrayCollection();
		$this->artifacts = new ArrayCollection();
	}


	public function getLivingCharacters() {
		return $this->getCharacters()->filter(
			function($entry) {
				return ($entry->isAlive()==true && $entry->isNPC()==false);
			}
		);
	}

	public function getActiveCharacters() {
		return $this->getCharacters()->filter(
			function($entry) {
				return ($entry->isAlive()==true && $entry->isNPC()==false && $entry->getRetired()==false);
			}
		);
	}

	public function getRetiredCharacters() {
		return $this->getCharacters()->filter(
			function($entry) {
				return ($entry->isAlive()==true && $entry->isNPC()==false && $entry->getRetired()==true);
			}
		);
	}

	public function getDeadCharacters() {
		return $this->getCharacters()->filter(
			function($entry) {
				return ($entry->isAlive()==false && $entry->isNPC()==false);
			}
		);
	}


	public function getNonNPCCharacters() {
		return $this->getCharacters()->filter(
			function($entry) {
				return ($entry->isNPC()==false);
			}
		);
	}

	public function isTrial() {
		// trial/free accounts cannot do some things
		if ($this->account_level <= 10) return true; else return false;
	}

	public function isNewPlayer() {
		$days = $this->getCreated()->diff(new \DateTime("now"), true)->days;
		if ($days < 30) {
			return true;
		} else {
			return false;
		}
	}

	public function isVeryNewPlayer() {
		$days = $this->getCreated()->diff(new \DateTime("now"), true)->days;
		if ($days < 7) {
			return true;
		} else {
			return false;
		}
	}

	public function getFreePlaces() {
		$months = floor($this->getCreated()->diff(new \DateTime("now"), true)->days/14);
		$count = 0;
		foreach ($this->getCharacters() as $character) {
			foreach ($character->getCreatedPlaces() as $place) {
				if (!$place->getDestroyed()) {
					$count++;
				}
			}
		}
		if ($this->account_level >= 20) {
			$mod = 2;
		} else {
			$mod = 1;
		}
		return ($months * $mod) - $count;
	}

	public function isBanned() {
		if ($this->hasRole('ROLE_BANNED_MULTI')) {
			return 'multi';
		}
		if ($this->hasRole('ROLE_BANNED_TOS')) {
			return 'tos';
		}
		return false;
	}
	
}
