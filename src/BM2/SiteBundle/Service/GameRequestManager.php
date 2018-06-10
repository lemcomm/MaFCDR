<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\GameRequest;
use BM2\SiteBundle\Entity\House;
use BM2\SiteBundle\Entity\Place;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Entity\Soldier;
use BM2\SiteBundle\Entity\EquipmentType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;

class GameRequestManager {

	/* This is the GameRequest Manager file, responsible for parsing any and all player to player requests in the game, creating, storing, deleting, etc.
	While the bulk of a GameRequest is setup to have foreign key constraints in the database, meaning it's relatively robust, certain parts of it do not.
	Each field will be covered here, so it's both easy to look things up, and so you know what everything is meant to do.
	These are listed in the format you need to type to get/set them, with descriptions following.

			STANDARD INFORMATION
		Id 			-> Unique database ID. Incrememnts from 1, perpetually. Created automatically.
		Type			-> String. Type of request. Unlike other types, accepts any input. Only set by server. Mandatory.
		Created			-> Datetime. When the request was made. Mandatory.
		Expires			-> Datetime. When the request expires. Optional.
		NumberValue		-> Float. For numeric values. Optional.
		StringValue		-> String. For text values. Optional.
		Subject			-> String. The subject of the request. Some will be automated, others not.
		Text			-> Text. The body of a message to accompany a request. Optional.
		Accepted		-> Boolean. Stores whether the request was accepted or not. Not set initially.
		Rejected		-> Boolean. Stores whether the request was refused or not. Not set initially.
			REQUESTOR INFORMATION -- Who/what made the request. Only one should be set, as appropriate. Reverses as "Requests".
		FromCharacter		-> Character.
		FromSettlement		-> Settlement.
		FromRealm		-> Realm.
		FromHouse		-> House.
			REQUESTEE INFORMATION -- Who/what is a request is made to. Only one should be set, as appropriate. Reverses as "RelatedRequests".
		ToCharacter		-> Character.
		ToSettlement		-> Settlement.
		ToRealm			-> Realm.
		ToHouse			-> House.
			REQUESTED INFORMATION -- Who/what is being requested. For sanity's sake, just set one, unless you're feeling brave. Reverses as "PartOfRequests".
		IncludeCharacter	-> Character.
		IncludeSettlement	-> Settlement.
		IncludeRealm		-> Realm.
		IncludeHouse		-> House.
		IncludeSoldiers		-> Soldiers. Array.
		IncludeEquipment	-> Equipment. Does not reverse.

	It is highly recommended that if you expand this file, use the existing code as a guide for how to make new requests.

	For simplicity of use, the only other service this file should interact with is Doctrine's Entity Manager.
	All acceptance/refusal actions should be handled either in the requests's respective service or controller. */

	protected $em;
	
	public function __construct(EntityManager $em) {
		$this->em = $em;
	}

	/* THE FOLLOWING IS PROVIDED FOR TEMPLATING PURPOSES ONLY. DO NOT USE "makeRequest" TO MAKE A REQUEST. 
	Use a situational method, like "newRequestFromCharacterToHouse", or make a new situational method if one doesn't exist.*/

	public function makeRequest($type, $expires = null, $numberValue = null, $stringValue = null, $subject = null, $text = null, Character $fromChar = null, Settlement $fromSettlement = null, Realm $fromRealm = null, House $fromHouse = null, Character $toChar = null, Settlement $toSettlement = null, Realm $toRealm = null, House $toHouse = null, Character $includeChar = null, Settlement $includeSettlement = null, Realm $includeRealm = null, House $includeHouse = null, Soldier $includeSoldiers = null, EquipmentType $includeEquipment = null) {
		$GR = new GameRequest();
		$this->em->persist($GR);
		$GR->setType($type);
		$GR->setCreated(new \DateTime("now"));
		if ($expires) {
			$GR->setExpires($expires);
		}
		if ($numberValue) {
			$GR->setNumberValue($numberValue);
		}
		if ($stringValue) {
			$GR->setStringValue($stringValue);
		}
		if ($subject) {
			$GR->setSubject($subject);
		}
		if ($text) {
			$GR->setText($text);
		}
		if ($fromChar) {
			$GR->setFromCharacter($fromChar);
		}
		if ($fromSettlement) {
			$GR->setFromSettlement($fromSettlement);
		}
		if ($fromRealm) {
			$GR->setFromRealm($fromRealm);
		}
		if ($fromHouse) {
			$GR->setFromHouse($fromHouse);
		}
		if ($toChar) {
			$GR->setToCharacter($toChar);
		}
		if ($toSettlement) {
			$GR->setToSettlement($toSettlement);
		}
		if ($toRealm) {
			$GR->setToRealm($toRealm);
		}
		if ($toHouse) {
			$GR->setToHouse($toHouse);
		}
		if ($includeChar) {
			$GR->setIncludeCharacter($includeChar);
		}
		if ($includeSettlement) {
			$GR->setIncludeSettlement($includeSettlement);
		}
		if ($includRealm) {
			$GR->setIncludeRealm($includeRealm);
		}
		if ($includeHouse) {
			$GR->setIncludeHouse($includeHouse);
		}
		if ($includeSoldiers) {
			foreach ($includeSoldiers as $soldier) {
				$GR->addIncludeSoldiers($soldier);
				$soldier->addPartOfRequests($GR);
			}
		}
		if ($includeEquipment) {
			foreach ($includeEquipment as $equip) {
				$GR->addIncludeEquipment($equip);
			}
		}
		$this->em->flush();
	}

	public function newRequestFromCharacterToHouse ($type, $expires = null, $numberValue = null, $stringValue = null, $subject = null, $text = null, Character $fromChar = null, House $toHouse = null, Character $includeChar = null, Settlement $includeSettlement = null, Realm $includeRealm = null, House $includeHouse = null, Soldier $includeSoldiers = null, EquipmentType $includeEquipment = null) {
		$GR = new GameRequest();
		$this->em->persist($GR);
		$GR->setType($type);
		$GR->setCreated(new \DateTime("now"));
		if ($expires) {
			$GR->setExpires($expires);
		}
		if ($numberValue) {
			$GR->setNumberValue($numberValue);
		}
		if ($stringValue) {
			$GR->setStringValue($stringValue);
		}
		if ($subject) {
			$GR->setSubject($subject);
		}
		if ($text) {
			$GR->setText($text);
		}
		$GR->setFromCharacter($fromChar);
		$GR->setToHouse($toHouse);
		if ($includeChar) {
			$GR->setIncludeCharacter($includeChar);
		}
		if ($includeSettlement) {
			$GR->setIncludeSettlement($includeSettlement);
		}
		if ($includeRealm) {
			$GR->setIncludeRealm($includeRealm);
		}
		if ($includeHouse) {
			$GR->setIncludeHouse($includeHouse);
		}
		if ($includeSoldiers) {
			foreach ($includeSoldiers as $soldier) {
				$GR->addIncludeSoldiers($soldier);
				$soldier->addPartOfRequests($GR);
			}
		}
		if ($includeEquipment) {
			foreach ($includeEquipment as $equip) {
				$GR->addIncludeEquipment($equip);
			}
		}
		$this->em->flush();
	}
}
