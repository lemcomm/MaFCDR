<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Event;
use BM2\SiteBundle\Entity\MailEntry;
use BM2\SiteBundle\Entity\User;
use Doctrine\ORM\EntityManager;

class DiscordIntegrator {

	protected $em;
	protected $appstate;
	protected $trans;
	protected $generalHook;

	public function __construct(EntityManager $em, $translator, AppState $appstate, $discord_webhook_general) {
		$this->em = $em;
		$this->appstate = $appstate;
		$this->trans = $translator;
		$this->generalHook = $discord_webhook_general;
	}

	public function pushToGeneral($text) {
		$webhook = $this->generalHook;
		$data = ['content' => $text];
		$jsonData = json_encode($data);
		$curl = curl_init($webhook);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
		curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($curl);
	}

}
