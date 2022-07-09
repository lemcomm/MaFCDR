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
	protected $olympusHook;
	protected $paymentsHook;

	public function __construct(EntityManager $em, $translator, AppState $appstate, $discord_webhook_general, $discord_webhook_olympus, $paymentsHook) {
		$this->em = $em;
		$this->appstate = $appstate;
		$this->trans = $translator;
		$this->generalHook = $discord_webhook_general;
		$this->olympusHook = $discord_webhook_olympus;
		$this->paymentsHook = $paymentsHook;
	}

	private function curlToDiscord($json, $webhook) {
		$curl = curl_init($webhook);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
		curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($curl);
	}

	public function pushToGeneral($text) {
		if ($this->generalHook) {
			$this->curlToDiscord(json_encode(['content' => $text]), $this->generalHook);
		}
	}

	public function pushToOlympus($text) {
		if ($this->olympusHook) {
			$this->curlToDiscord(json_encode(['content' => $text]), $this->olympusHook);
		}
	}

	public function pushToPayments($text) {
		if ($this->paymentsHook) {
			$this->curlToDiscord(json_encode(['content' => $text]), $this->paymentsHook);
		}
	}

}
