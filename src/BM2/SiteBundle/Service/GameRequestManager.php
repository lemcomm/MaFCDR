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
					   If this is set FALSE, the Expires date will be updated to a week out, and after that week the game will purge the request. If set to TRUE and expirations is met, this request will be purged.

			REQUESTOR INFORMATION -- Who/what made the request. Only one should be set, as appropriate. Reverses as "Requests".
		FromCharacter		-> Character.
		FromSettlement		-> Settlement.
		FromRealm		-> Realm.
		FromHouse		-> House.
		FromPlace		-> Place.

			REQUESTEE INFORMATION -- Who/what is a request is made to. Only one should be set, as appropriate. Reverses as "RelatedRequests".
		ToCharacter		-> Character.
		ToSettlement		-> Settlement.
		ToRealm			-> Realm.
		ToHouse			-> House.
		ToPlace			-> Place.

			REQUESTED INFORMATION -- Who/what is being requested. For sanity's sake, just set one, unless you're feeling brave. Reverses as "PartOfRequests".
		IncludeCharacter	-> Character.
		IncludeSettlement	-> Settlement.
		IncludeRealm		-> Realm.
		IncludeHouse		-> House.
		IncludePlace		-> Place.
		IncludeSoldiers		-> Soldiers. Array.
		IncludeEquipment	-> Equipment. Does not reverse.

	It is highly recommended that if you expand this file, use the existing code as a guide for how to make new requests.

	For simplicity of use, the only other service this file should interact with is Doctrine's Entity Manager. This ensures that you can load this service into any other service without creating a dependency loop.
	All acceptance/refusal logic should be handled in the GameRequest Controller. In short the GameRequest logic is as follows:

		1. User inputs the data for the request for a form and submits it from a controller route.
		2. That route verifies the data submitted is accurate and then submits it to this Service.
		3. This service builds the GameRequest and stores it in the database.
		4. Another route, likely the bm2_gamerequest_manage route allows the receiving user to interact with pending/active requests.
		5. That controller presents the approve/deny actions to the user.
		6. When a user accepts/denies a request, it is handled by the GameRequest Controller, either by the bm2_gamerequest_approve or the bm2_gamerequest_deny routes, respectively. 
		   These routes verify the user has authority to handle that request, carry out all actions of the request, and reroute the user to the appropriate page afterwards.
		7. When an approved request reaches it's expiration date OR a week after a denied request was denied has been reached, GameRunner will purge that request from the database on the next hourly turn.

	If you need more detailed information on these, contact a M&F developer--we recommend Andrew. */

	protected $em;
	
	public function __construct(EntityManager $em) {
		$this->em = $em;
	}

	public function findAllManageableRequests(Character $char) {
		$realms = $char->findRealms();
		$realmIDs =  [];
		foreach ($realms as $realm) {
			$realmIDs[] = $realm->getId();
		}
		$counter = $this->em->createQuery('SELECT COUNT(r) FROM BM2SiteBundle:GameRequest r JOIN r.to_settlement s JOIN r.to_house h JOIN r.to_place p WHERE (r.to_character = :char OR s.owner = :char OR r.to_realm IN (:realms) OR h.head = :char OR p.owner = :char) AND r.accepted IS NULL OR r.accepted = true');
		$counter->setParameters(array('char'=>$char, 'realms'=>$realmIDs));
		if ($counter->getSingleScalarResult() > 0) {
			$query = $this->em->createQuery('SELECT r FROM BM2SiteBundle:GameRequest r JOIN r.to_settlement s JOIN r.to_house h JOIN r.to_place p WHERE (r.to_character = :char OR s.owner = :char OR r.to_realm IN (:realms) OR h.head = :char OR p.owner = :char) AND r.accepted IS NULL OR r.accepted = true GROUP BY r.accepted ASC ORDER BY r.created ASC');
			$query->setParameters(array('char'=>$char, 'realms'=>$realmIDs));
			return $query->getResult();
		} else {
			return null;
		}
	}

	/* THE FOLLOWING IS PROVIDED FOR TEMPLATING PURPOSES ONLY. For performance reasons do not use this function to actually generate a request.
	Use a situational method, like "newRequestFromCharacterToHouse", or make a new situational method if one doesn't exist.*/

	public function makeRequest($type, $expires = null, $numberValue = null, $stringValue = null, $subject = null, $text = null, Character $fromChar = null, Settlement $fromSettlement = null, Realm $fromRealm = null, House $fromHouse = null, Place $fromPlace, Character $toChar = null, Settlement $toSettlement = null, Realm $toRealm = null, House $toHouse = null, Place $toPlace, Character $includeChar = null, Settlement $includeSettlement = null, Realm $includeRealm = null, House $includeHouse = null, Place $includePlace, Soldier $includeSoldiers = null, EquipmentType $includeEquipment = null) {
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
		if ($fromPlace) {
			$GR->setFromPlace($fromPlace);
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
		if ($toPlace) {
			$GR->setToPlace($toPlace);
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
		if ($includePlace) {
			$GR->setIncludePlace($includePlace);
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

	public function newRequestFromCharactertoSettlement ($type, $expires = null, $numberValue = null, $stringValue = null, $subject = null, $text = null, Character $fromChar = null, Settlement $toSettlement = null) {
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
		if ($toSettlement) {
			$GR->setToSettlement($toSettlement);
		}
		$this->em->flush();
	}
}
