<?php

namespace BM2\SiteBundle\Service;

use BM2\SiteBundle\Entity\Code;
use BM2\SiteBundle\Entity\CreditHistory;
use BM2\SiteBundle\Entity\User;
use BM2\SiteBundle\Entity\UserPayment;
use Doctrine\ORM\EntityManager;
use Monolog\Logger;
use Patreon\API as PAPI;
use Patreon\OAuth as POA;
use PayPal\Auth\OAuthTokenCredential as PPOATC;
use PayPal\Rest\ApiContext as PPAC;
use Symfony\Component\Translation\TranslatorInterface;

class PaymentManager {

	protected $em;
	protected $usermanager;
	protected $mailer;
	protected $translator;
	protected $logger;
	private $mailman;
	private $ruleset;
	private $paypalClientId;
	private $paypalSecret;
	private $rootDir;
	private $env;


	// FIXME: type hinting for $translator removed because the addition of LoggingTranslator is breaking it
	public function __construct(EntityManager $em, UserManager $usermanager, \Swift_Mailer $mailer, TranslatorInterface $translator, Logger $logger, MailManager $mailman, $ruleset, $paypalClientId, $paypalSecret, $rootDir, $env) {
		$this->em = $em;
		$this->usermanager = $usermanager;
		$this->mailer = $mailer;
		$this->translator = $translator;
		$this->logger = $logger;
		$this->mailman = $mailman;
		$this->ruleset = $ruleset;
		$this->paypalClientId = $paypalClientId;
		$this->paypalSecret = $paypalSecret;
		$this->rootDir = $rootDir;
		$this->env = $env;
	}

	public function getPaymentLevels(User $user = null, $system = false) {
		if ($this->ruleset === 'maf') {
			return [
				 0 =>	array('name' => 'storage',	'characters' =>    0, 'fee' =>   0, 'selectable' => false, 'patreon'=>false, 'creator'=>false),
				10 =>	array('name' => 'trial',	'characters' =>   15, 'fee' =>   0, 'selectable' => true,  'patreon'=>false, 'creator'=>false),
				23 =>	array('name' => 'supporter',	'characters' =>	  15, 'fee' => 200, 'selectable' => true,  'patreon'=>false,	'creator'=>false),
				20 =>	array('name' => 'basic',	'characters' =>   10, 'fee' => 200, 'selectable' => false,  'patreon'=>false, 'creator'=>false),
				21 =>	array('name' => 'volunteer',	'characters' =>   10, 'fee' =>   0, 'selectable' => false, 'patreon'=>false, 'creator'=>false),
				22 =>   array('name' => 'traveler',	'characters' =>   10, 'fee' =>   0, 'selectable' => false,  'patreon'=>200,   'creator'=>'andrew'),
				40 =>	array('name' => 'intense',	'characters' =>   25, 'fee' => 300, 'selectable' => false,  'patreon'=>false, 'creator'=>false),
				41 =>	array('name' => 'developer',	'characters' =>   25, 'fee' =>   0, 'selectable' => false, 'patreon'=>false, 'creator'=>false),
				42 =>   array('name' => 'explorer',	'characters' =>   25, 'fee' =>   0, 'selectable' => false,  'patreon'=>300,   'creator'=>'andrew'),
				50 =>	array('name' => 'ultimate',	'characters' =>   50, 'fee' => 400, 'selectable' => false,  'patreon'=>false, 'creator'=>false),
				51 =>   array('name' => 'explorer+',	'characters' =>   50, 'fee' =>   0, 'selectable' => false,  'patreon'=>400,   'creator'=>'andrew'),
			];
		}
	}

	public function calculateUserFee(User $user) {
		$days = 0;
		$now = new \DateTime("now");
		if ($user->getLastLogin()) {
			$diff = $user->getLastLogin()->diff($now);
			$days = $diff->d;
		}

		$fees = $this->getPaymentLevels();
		if ($days>60) {
			$fee = $fees[0]['fee'];
		} else {
			$fee = $fees[$user->getAccountLevel()]['fee'];
			if ($user->getVipStatus() >= 20) {
				// legend or immortal = free basic account
				$fee = max(0, $fee - $fees[20]['fee']);
			}
		}
		return $fee;
	}

	public function getCostOfHeraldry() {
		return 250;
	}


	public function calculateRefund(User $user) {
		$today = new \DateTime("now");
		$days = $today->diff($user->getPaidUntil())->format("%a");
		$today->modify('+1 month -1 day');
		$month = $today->diff(new \DateTime("now"))->format("%a");

		$refund = ceil( ($days/$month) * $this->calculateUserFee($user) );
		return $refund;
	}

	public function calculateUserMaxCharacters(User $user) {
		$fees = $this->getPaymentLevels();
		return $fees[$user->getAccountLevel()]['characters'];
	}

	public function paymentCycle($patronsOnly = false) {
		$this->logger->info("Payment Cycle...");
		$free = 0;
		$patronCount = 0;
		$active = 0;
		$expired = 0;
		$storage = 0;
		$credits = 0;
		$bannedcount = 0;
		$bannedquery = $this->em->createQuery("SELECT u FROM BM2SiteBundle:User u WHERE u.account_level > 0 AND u.roles LIKE '%ROLE_BANNED%'");
		foreach ($bannedquery->getResult() as $banned) {
			$bannedcount++;
			$this->changeSubscription($banned, 0);
			$banned->setNotifications(FALSE);
			$banned->setNewsletter(FALSE);
			$bannedusername = $banned->getUsername();
			$this->logger->info("$bannedusername has been banned, and email notifications have been disabled.");
			$this->em->flush();
		}
		$this->logger->info("Refreshing patreon pledges for users with connected accounts.");
		$uCount = 0;
		$pledges = 0;
		$patronquery = $this->em->createQuery('SELECT u, p FROM BM2SiteBundle:User u JOIN u.patronizing p');
		foreach ($patronquery->getResult() as $user) {
			foreach ($user->getPatronizing() as $patron) {
				if ($patron->getExpires() < $now) {
					$this->refreshPatreonTokens($patron);
				}
				$this->refreshPatreonPledge($patron);
				$pledges++;
			}
			$uCount++;
		}
		$this->em->flush();
		$this->logger->info("Refreshed ".$pledges." pledges for ".$uCount." users.");

		$now = new \DateTime("now");
		$query = $this->em->createQuery('SELECT u FROM BM2SiteBundle:User u WHERE u.account_level > 0 AND u.paid_until < :now');
		$query->setParameters(array('now'=>$now));

		$this->logger->info("  User Subscription Processing...");
		foreach ($query->getResult() as $user) {
			#$this->logger->info("  --Calculating ".$user->getUsername()." (".$user->getId().")...");
			$myfee = $this->calculateUserFee($user);
			$levels = $this->getPaymentLevels();
			if ($myfee > 0) {
				$this->logger->info("  --Calculating fee for ".$user->getUsername()." (".$user->getId().")...");
				if ($this->spend($user, 'subscription', $myfee, true)) {
					$this->logger->info("    --Credit spend successful...");
					$active++;
					$credits += $myfee;
				} else {
					$this->logger->info("    --Credit spend failed. Reducing account...");
					// not enough credits left! - change to trial
					$user->setAccountLevel(10);
					$this->ChangeNotification($user, 'expired', 'expired2');
					// TODO: check that this recalculates correctly if someone is far beyond the due date and then renews subscription
					$expired++;
				}
			} elseif ($levels[$user->getAccountLevel()]['patreon'] != false) {
				#$this->logger->info("    --Patron detected...");
				$patreonLevel = $levels[$user->getAccountLevel()]['patreon'];
				$sufficient = false;
				#TODO: We'll need to expand this to support other creators, if we add any.
				foreach ($user->getPatronizing() as $patron) {
					$this->logger->info("    --Supporter of creator ".$patron->getCreator()->getCreator()."...");
					$status = null;
					$entitlement = null;

					$this->logger->info("    --Status of '".$status."'; entitlement of ".$entitlement."; versus need of ".$patreonLevel." for sub level ...");

					if ($patreonLevel <= $entitlement) {
						#$this->logger->info("    --Pledge is sufficient...");
						$sufficient = true;
					}
				}
				if (!$sufficient) {
					$this->logger->info("    --Pledge insufficient, reducing subscription...");
					# insufficient pledge level
					$user->setAccountLevel(10);
					$this->ChangeNotification($user, 'insufficient', 'insufficient2');
					$expired++;
				} else {
					$this->logger->info("    --Pledge sufficent, running spend routine...");
					$this->spend($user, 'subscription', $myfee, true);
					$active++;
					$patronCount++;
					# TODO: Give overpledge back as credits?
				}
			} else {
				#$this->logger->info("    --Non-payer detected, either trial or dev account...");
				if ($user->getLastLogin()) {
					$inactive_days = $user->getLastLogin()->diff(new \DateTime("now"), true)->days;
				} else {
					$inactive_days = $user->getCreated()->diff(new \DateTime("now"), true)->days;
				}
				if ($inactive_days > 60) {
					#$this->logger->info("    --Account inactive, storing account...");
					// after 2 months, we put you into storage
					$user->setAccountLevel(0);
					$storage++;
				} else {
					#$this->logger->info("    --Accont active, logging as free...");
					$free++;
				}
			}
		}
		$this->logger->info("  Cycle ended. Flushing...");
		$this->em->flush();
		return array($free, $patronCount, $active, $credits, $expired, $storage, $bannedcount);
	}

	public function refreshPatreonTokens($patron) {
		$now = new \DateTime("now");
		if ($patron->getExpires() < $now) {
			$creator = $patron->getCreator();
			$poa = new POA($creator->getClientId(), $creator->getClientSecret());
			$tokens = $poa->refresh_token($patron->getRefreshToken());
			$patron->setAccessToken($tokens['access_token']);
			$patron->setRefreshToken($tokens['refresh_token']);
			$patron->setExpires(new \DateTime('+'.$tokens['expires_in'].' seconds'));
		}
	}

	public function refreshPatreonPledge($patron, $args = ['skip_read_from_cache'=>true, 'skip_add_to_cache'=>true]) {
		$papi = new PAPI($patron->getAccessToken());
		$member = $papi->fetch_user($args);
		if (!$patron->getPatreonId()) {
			$patron->setPatreonId($member['data']['id']);
		}
		$status = $member['included'][0]['attributes']['patron_status'];
		$patron->setStatus($status);
		$entitlement = $member['included'][0]['attributes']['currently_entitled_amount_cents'];
		$lifetime = $member['included'][0]['attributes']['lifetime_support_cents'];
		$patron->setCurrentAmount($entitlement);
		if ($patron->getCredited() != $lifetime) {
			if ($patron->getCredited() === null) {
				$dif = $lifetime;
			} else {
				$dif = $lifetime - $patron->getCredited();
			}
			$dif = $dif / 100; #Patreon provides in cents. We want full dollars!
			$this->account($patron->getUser(), 'Patron Credit', 'USD', $dif);
			$patron->setCredited($lifetime); #We do track it in cents though.
		}
		return [$status, $entitlement];
	}

	public function getPayPalAPIContext() {
		$api = new PPAC(
			new PPOATC(
				$this->paypalClientId,
				$this->paypalSecret
				)
			);
		if ($env === 'prod') {
			$api->setConfig([
				'mode' => 'live',
				'log.LogEnabled' => true,
				'log.FileName' => $this->rootDir.'app/logs/PayPal.log',
				'log.LogLevel' => 'INFO',
				'cache.enabled' => false,
			]);
		} else {
			$api->setConfig([
				'mode' => 'sandbox',
				'log.LogEnabled' => true,
				'log.FileName' => $this->rootDir.'app/logs/PayPal.log',
				'log.LogLevel' => 'DEBUG',
				'cache.enabled' => false,
			]);
		}

		return $api;
	}

	/*
	public function refreshPatreonPledge($patron, $args = ['skip_read_from_cache'=>true, 'skip_add_to_cache'=>true]) {
		$papi = new PAPI($patron->getAccessToken());
		$member = $papi->fetch_user($args);
		if (!$patron->getPatreonId()) {
			$patron->setPatreonId($member['data']['id']);
		}
		echo "<pre>".print_r($member,true)."</pre>";
		$status = $member['included'][0]['attributes']['patron_status'];
		$patron->setStatus($status);
		$entitlement = $member['included'][0]['attributes']['currently_entitled_amount_cents'];
		$patron->setCurrentAmount($entitlement);
		return [$status, $entitlement];
	}
	*/

	private function ChangeNotification(User $user, $subject, $text) {
		$subject = $this->translator->trans("account.payment.mail.".$subject, array());
		$content = $this->translator->trans("account.payment.mail.".$text, array());

		$message = \Swift_Message::newInstance()
			->setSubject($subject)
			->setFrom('mafserver@lemuriacommunity.org')
			->setReplyTo('mafteam@lemuriacommunity.org')
			->setTo($user->getEmail())
			->setBody(strip_tags($content))
			->addPart($content, 'text/html');
		$this->mailer->send($message);
	}

	public function changeSubscription(User $user, $newlevel) {
		if (!array_key_exists($newlevel, $this->getPaymentLevels())) {
			return false;
		}
		$oldlevel = $user->getAccountLevel();
		$oldpaid = $user->getPaidUntil();

		$levels = $this->getPaymentLevels();
		if ($levels[$newlevel]['patreon'] != false) {
			$valid = false;
			foreach ($user->getPatronizing() as $patron) {
				if ($levels[$newlevel]['creator'] == $patron->getCreator()->getCreator()) {
					if ($patron->getStatus() == 'active_patron' && $patron->getCurrentAmount() >= $levels[$newlevel]['patreon']) {
						$valid = true;
						if ($valid) {
							break;
						}
					}
				}
			}
			if ($valid) {
				if ($user->getRestricted()) {
					$user->setRestricted(false);
				}
				$refund = $this->calculateRefund($user);
				$user->setAccountLevel($newlevel);
				$user->setPaidUntil(new \DateTime("now"));
				$this->em->flush();
				return true;
			}
			# Either they are a valid patron, and the above returns true. Or they aren't, and this call fails. The rest doesn't matter.
			return false;
		}

		$fee = $this->calculateUserFee($user);
		$refund = $this->calculateRefund($user);

		if ($fee > $user->getCredits()+$refund) {
			return false;
		} else {
			if ($refund>0) {
				$this->spend($user, 'refund', -$refund, false);
			}
			$user->setAccountLevel($newlevel);
			$user->setPaidUntil(new \DateTime("now"));
			$check = $this->spend($user, 'subscription', $fee, true);
			if ($check) {
				// reset account restriction, so it is recalculated
				if ($user->getRestricted()) {
					$user->setRestricted(false);
					$this->em->flush();
				}
				return true;
			} else {
				// this should never happen - alert me
				$this->logger->alert('error in change subscription for user '.$user->getId().", change from $oldlevel to $newlevel");
				return false;
			}
		}
	}


	public function account(User $user, $type, $currency, $amount, $transaction=null) {
		$credits = $amount*100; // if this ever changes, make sure to update texts mentioning it (e.g. description3)
		switch ($currency) {
			case 'USD':		$credits *= 1.0; break;
			default:
				$this->logger->alert("unknown currency $currency in accounting for user #{$user->getId()}, transaction type $type, please add $amount manually.");
				return false;
		}
		$credits = ceil($credits);
		$original = $credits;
		if ($type !== 'Patron Credit') {
			if ($amount >= 100) {
				$bonus = $credits * 0.5;
			} elseif ($amount >= 50) {
				$bonus = $credits * 0.4;
			} elseif ($amount >= 20) {
				$bonus = $credits * 0.3;
			} elseif ($amount >= 10) {
				$bonus = $credits * 0.2;
			} elseif ($amount >= 5) {
				$bonus = $credits * 0.1;
			} else {
				$bonus = 0;
			}
		} else {
			$bonus = $credits * 0.5;
		}

		$credits = $credits + $bonus;
		$credits = ceil($credits); # Not that this should ever be a decimal but...

		if ($user->getPayments()->isEmpty()) {
			$first = true;
		} else {
			$first = false;
		}

		$payment = new UserPayment;
		$payment->setTs(new \DateTime("now"));
		$payment->setCurrency($currency);
		$payment->setAmount($amount);
		$payment->setType($type);
		$payment->setUser($user);
		$payment->setCredits($original);
		$payment->setBonus($bonus);
		$payment->setTransactionCode($transaction);
		$this->em->persist($payment);
		$user->addPayment($payment);

		$history = new CreditHistory;
		$history->setTs(new \DateTime("now"));
		$history->setCredits($original);
		$history->setBonus($bonus);
		$history->setUser($user);
		$history->setType($type);
		$history->setPayment($payment);
		$this->em->persist($history);
		$user->addCreditHistory($history);

		$user->setCredits($user->getCredits()+$credits);

		if ($first) {
			// give us our free vanity item
			$user->setArtifactsLimit(max(1, $user->getArtifactsLimit()));

			// check if we had a friend code
			$query = $this->em->createQuery('SELECT c FROM BM2SiteBundle:Code c WHERE c.used_by = :me AND c.sender IS NOT NULL AND c.sender != :me ORDER BY c.used_on ASC');
			$query->setParameter('me', $user);
			$query->setMaxResults(1);
			$code = $query->getOneOrNullResult();
			if ($code) {
				$sender = $code->getSender();
				$value = round(min($credits, $code->getCredits()) / 2);

				$h = new CreditHistory;
				$h->setTs(new \DateTime("now"));
				$h->setCredits($value);
				$h->setUser($sender);
				$h->setType('friendinvite');
				$this->em->persist($h);
				$user->addCreditHistory($h);

				$sender->setCredits($sender->getCredits()+$value);
				$this->usermanager->updateUser($sender, false);

				$text = $this->translator->trans('account.invite.mail2.body', array("%mail%"=>$user->getEmail(), "%credits%"=>$value));
				$numSent = $this->mailman->sendEmail($sender->getEmail(), $this->translator->trans('account.invite.mail2.subject'), $text);
				$this->logger->info('sent friend subscriber email: ('.$numSent.') - '.$text);
			}
		}

		// TODO: this is not quite complete, what about people going into negative credits?
		$this->usermanager->updateUser($user, false);
		$this->em->flush();
		return true;
	}

	public function redeemHash(User $user, $hash) {
		$code = $this->em->getRepository('BM2SiteBundle:Code')->findOneByCode($hash);
		if ($code) {
			return array($code, $this->redeemCode($user, $code));
		} else {
			return array(null, "error.payment.nosuchcode");
		}
	}

	public function redeemCode(User $user, Code $code) {
		if ($code->getUsed()) {
			$this->logger->alert("user #{$user->getId()} tried to redeem already-used code {$code->getId()}");
			return "error.payment.already";
		}

		if ($code->getSentToEmail() && $code->getLimitToEmail() && $code->getSentToEmail() != $user->getEmail()) {
			$this->logger->alert("user #{$user->getId()} tried to redeem code not for him - code #{$code->getId()}");
			return "error.payment.notforyou";
		}

		if ($code->getCredits() > 0) {
			$history = new CreditHistory;
			$history->setTs(new \DateTime("now"));
			$history->setCredits($code->getCredits());
			$history->setUser($user);
			$history->setType("code");
			$this->em->persist($history);
			$user->addCreditHistory($history);

			$user->setCredits($user->getCredits()+$code->getCredits());
		}

		if ($code->getVipStatus() && $code->getVipStatus() > $user->getVipStatus()) {
			// TODO: report back if this doesn't change our status
			$user->setVipStatus($code->getVipStatus());
		}
		// TODO: unlock characters, also check if we were due a payment - how ?
		$this->usermanager->updateUser($user, false);

		$code->setUsed(true);
		$code->setUsedOn(new \DateTime("now"));
		$code->setUsedBy($user);

		$this->em->flush();

		return true;
	}

	public function createCode($credits, $vip_status=0, $sent_to=null, User $sent_from=null, $limit=false) {
		$code = new Code;
		$code->setCode(sha1(time()."mafcode".mt_rand(0,1000000)));
		$code->setCredits($credits);
		$code->setVipStatus($vip_status);
		$code->setUsed(false);
		$code->setSentOn(new \DateTime("now"));
		if ($sent_from) {
			$code->setSender($sent_from);
		}
		if ($sent_to) {
			$code->setSentToEmail($sent_to);
		} else {
			$code->setSentToEmail("");
		}
		$code->setLimitToEmail($limit);
		$this->em->persist($code);
		$this->em->flush();
		return $code;
	}


	public function spend(User $user, $type, $credits, $renew_subscription=false) {
		if ($credits>0 && $user->getCredits()<$credits) {
			return false;
		}
		$history = new CreditHistory;
		$history->setTs(new \DateTime("now"));
		$history->setCredits(-$credits);
		$history->setUser($user);
		$history->setType($type);
		$this->em->persist($history);
		$user->addCreditHistory($history);

		$user->setCredits($user->getCredits()-$credits);
		if ($renew_subscription) {
			// NOTICE: This will add +1 to the month value and can skip into the following month
			// example: January 31st + 1 month == March 3rd (because there is no February 31st)
			$paid = clone $user->getPaidUntil();
			$paid->modify('+1 month');
			$user->setPaidUntil($paid);
		}
		$this->usermanager->updateUser($user, false);
		$this->em->flush();
		$this->logger->info("Payment: User ".$user->getId().", $type, $credits credits");
		return true;
	}


	public function log_info($text) {
		$this->logger->info($text);
	}

	public function log_error($text) {
		$this->logger->error($text);
	}

}
