<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Patreon;
use BM2\SiteBundle\Entity\Patron;
use BM2\SiteBundle\Form\CultureType;
use BM2\SiteBundle\Form\GiftType;
use BM2\SiteBundle\Form\SubscriptionType;
use Patreon\API as PAPI;
use Patreon\OAuth as POA;
use PayPal;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

/**
 * @Route("/payment")
 */
class PaymentController extends Controller {

	private $giftchoices = array(100, 200, 300, 400, 500, 600, 800, 1000, 1200, 1500, 2000, 2500);

	private function fetchPatreon($creator = null) {
		$em = $this->getDoctrine()->getManager();
		if (!$creator) {
			$query = $em->createQuery("SELECT p FROM BM2SiteBundle:Patreon p WHERE p.id > 0");
			$result = $query->getResult();
		} else {
			$query = $em->createQuery("SELECT p FROM BM2SiteBundle:Patreon p WHERE p.creator = :name");
			$query->setParameters(["name"=>$creator]);
			$result = $query->getSingleResult();
		}
		return $result;
	}

	// FIXME: the secrets, etc. should probably not be in here, but in a safe place
	// PayPal
/*
	private $paypal_config = array (
			'mode' => 'sandbox' ,
			'acct1.UserName' => 'jb-us-seller_api1.paypal.com',
			'acct1.Password' => 'WX4WTU3S8MY44S7F',
			'acct1.Signature' => 'AFcWxV21C7fd0v3bYYYRCpSSRl31A7yDhhsPUU2XhtMoZXsWHFxu-RWy'
	);
*/
	private $paypal_config = array (
			'mode' => 'live',
			'acct1.UserName' => 'payment_api1.mightandfealty.com',
			'acct1.Password' => 'YWJ22BG45Z63LBU4',
			'acct1.Signature' => 'AuoUscunfGMRfST-cga6SnFHZjaMAhHW.dmiLV7HVrlT0SvatET9DLng'
	);

	/**
     * @Route("/", name="bm2_payment")
     */
	public function paymentAction(Request $request) {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		$user = $this->getUser();

		$form = $this->createFormBuilder()
			->add('hash', 'text', array(
				'required' => true,
				'label' => 'account.code.label',
			))
			->add('submit', 'submit', array('label'=>'account.code.submit'))
			->getForm();

		$redeemed = false;

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			list($code, $result) = $this->get('payment_manager')->redeemHash($user, $data['hash']);

			if ($result === true) {
				$redeemed = $code;
			} else {
				$form->addError(new FormError($this->get('translator')->trans($result)));
			}
		}

		return $this->render('Payment/payment.html.twig', [
			'form' => $form->createView(),
			'redeemed' => $redeemed
		]);
	}


	/**
	  * @Route("/paypal/{amount}", name="bm2_paypal", requirements={"amount"="\d+"})
	  */
	public function paypalAction($amount, Request $request) {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		$user = $this->getUser();

		$paypalService = new PayPal\Service\PayPalAPIInterfaceServiceService($this->paypal_config);
		$paymentDetails = $this->setup_payment_details($amount, $user->getId());

		$setECReqDetails = new PayPal\EBLBaseComponents\SetExpressCheckoutRequestDetailsType();
		$setECReqDetails->PaymentDetails[0] = $paymentDetails;
		$setECReqDetails->CancelURL = $this->generateUrl('bm2_paypal_cancel', array(), true);
		$setECReqDetails->ReturnURL = $this->generateUrl('bm2_paypal_success', array(), true);

		$setECReqType = new PayPal\PayPalAPI\SetExpressCheckoutRequestType();
		$setECReqType->Version = '104.0';
		$setECReqType->SetExpressCheckoutRequestDetails = $setECReqDetails;

		$setECReq = new PayPal\PayPalAPI\SetExpressCheckoutReq();
		$setECReq->SetExpressCheckoutRequest = $setECReqType;

		$setECResponse = $paypalService->SetExpressCheckout($setECReq);

		if (strtolower($setECResponse->Ack) == "success") {
			$token = $setECResponse->Token;
			return $this->redirect("https://www.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=".$token);
		} else {
			throw new HttpException(402, "PayPal Checkout failed");
		}
	}

	/**
	  * @Route("/paypal_success", name="bm2_paypal_success")
	  */
	public function paypalsuccessAction(Request $request) {
		$token = $request->query->get('token');
		$payer_id = $request->query->get('PayerID');

		$paypalService = new PayPal\Service\PayPalAPIInterfaceServiceService($this->paypal_config);
		$getExpressCheckoutDetailsRequest = new PayPal\PayPalAPI\GetExpressCheckoutDetailsRequestType($token);
		$getExpressCheckoutDetailsRequest->Version = '104.0';
		$getExpressCheckoutReq = new PayPal\PayPalAPI\GetExpressCheckoutDetailsReq();
		$getExpressCheckoutReq->GetExpressCheckoutDetailsRequest = $getExpressCheckoutDetailsRequest;

		$getECResponse = $paypalService->GetExpressCheckoutDetails($getExpressCheckoutReq);
		$details = $getECResponse->GetExpressCheckoutDetailsResponseDetails;
		$user_id = $details->Custom;
		$em = $this->getDoctrine()->getManager();
		$user = $em->getRepository('BM2SiteBundle:User')->find($user_id);
		if ($user) {
			$paymentDetails = $details->PaymentDetails;
			$OrderTotal = $details->PaymentDetails[0]->OrderTotal;
			if (strtolower($getECResponse->Ack) == "success") {
				$paypalService = new PayPal\Service\PayPalAPIInterfaceServiceService($this->paypal_config);
				$paymentDetails = $this->setup_payment_details($OrderTotal->value, $user_id);

				$DoECRequestDetails = new PayPal\EBLBaseComponents\DoExpressCheckoutPaymentRequestDetailsType();
				$DoECRequestDetails->PayerID = $payer_id;
				$DoECRequestDetails->Token = $token;
				$DoECRequestDetails->PaymentDetails[0] = $paymentDetails;

				$DoECRequest = new PayPal\PayPalAPI\DoExpressCheckoutPaymentRequestType();
				$DoECRequest->DoExpressCheckoutPaymentRequestDetails = $DoECRequestDetails;
				$DoECRequest->Version = '104.0';

				$DoECReq = new PayPal\PayPalAPI\DoExpressCheckoutPaymentReq();
				$DoECReq->DoExpressCheckoutPaymentRequest = $DoECRequest;

				$DoECResponse = $paypalService->DoExpressCheckoutPayment($DoECReq);

				if (strtolower($DoECResponse->Ack) == "success") {
					$info = $DoECResponse->DoExpressCheckoutPaymentResponseDetails->PaymentInfo[0];
					if (strtolower($info->PaymentStatus) == "completed") {
						$tx_id = $info->TransactionID;
						$amount = floatval($info->GrossAmount->value);
						$currency = $info->GrossAmount->currencyID;

						$this->get('payment_manager')->log_info("PayPal Payment callback: $amount $currency / for $user_id / tx_id: $tx_id");
						$this->get('payment_manager')->account($user, "PayPal Payment", $currency, $amount, $tx_id);
						return $this->redirectToRoute('bm2_payment');
					}
					throw new HttpException(402, "Payment not completed");
				} else {
					throw new HttpException(402, "Express Checkout response failed - ".$DoECResponse->Ack);
				}
			} else {
				throw new HttpException(402, "Express Checkout failed");
			}
		} else {
			$this->get('payment_manager')->log_error("Cannot find user for PayPal callback: $user_id");
            throw new HttpException(402, "Cannot find user data");
		}
	}

	/**
	  * @Route("/paypal_cancel", name="bm2_paypal_cancel")
	  */
	public function paypalcancelAction(Request $request) {
		throw new HttpException(402, "PayPal Checkout cancelled");
	}


	private function setup_payment_details($amount, $custom) {
		$paymentDetails= new PayPal\EBLBaseComponents\PaymentDetailsType();

		$itemDetails = new PayPal\EBLBaseComponents\PaymentDetailsItemType();
		$itemDetails->Name = 'Might & Fealty in-game credits';
		$itemAmount = $amount;
		$itemDetails->Amount = $itemAmount;
		$itemQuantity = '1';
		$itemDetails->Quantity = $itemQuantity;

		$paymentDetails->PaymentDetailsItem[0] = $itemDetails;

		$orderTotal = new PayPal\CoreComponentTypes\BasicAmountType();
		$orderTotal->currencyID = 'EUR';
		$orderTotal->value = $itemAmount * $itemQuantity;

		$paymentDetails->OrderTotal = $orderTotal;
		$paymentDetails->PaymentAction = 'Sale';
		$paymentDetails->Custom = $custom;

		return $paymentDetails;
	}

	/**
	  * @Route("/testpayment")
	  */
	public function testpaymentAction(Request $request) {
		$env = $this->get('kernel')->getEnvironment();
		if ($env != "dev" && $env != "test") {
			throw $this->createAccessDeniedException("test payments only available on dev and test");
		}
		$user = $this->getUser();

		$amount = $request->request->get("amount");
		$currency = $request->request->get("currency");

		if (in_array($amount, array(10,20)) && in_array($currency, array('USD', 'EUR'))) {
			$this->get('payment_manager')->account($user, "test", $currency, $amount);
		}


		return $this->redirectToRoute('bm2_payment');
	}

   /**
     * @Route("/credits")
     */
	public function creditsAction() {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		$user = $this->getUser();

		return $this->render('Payment/credits.html.twig', [
			'myfee' => $this->get('payment_manager')->calculateUserFee($user),
			'concepturl' => $this->generateUrl('bm2_site_default_paymentconcept')
		]);
	}

   /**
     * @Route("/subscription")
     */
	public function subscriptionAction(Request $request) {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		$user = $this->getUser();
		$levels = $this->get('payment_manager')->getPaymentLevels($user);

		$form = $this->createForm(new SubscriptionType($levels, $user->getAccountLevel()));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			// TODO: this should require an e-mail confirmation
			// TODO: should not allow lowering while above (new) character limit

			$check = $this->get('payment_manager')->changeSubscription($user, $data['level']);
			if ($check) {
				$this->addFlash('notice', 'account.sub.changed');
				return $this->redirectToRoute('bm2_site_payment_credits');
			}
		}

		return $this->render('Payment/subscription.html.twig', [
			'myfee' => $this->get('payment_manager')->calculateUserFee($user),
			'refund' => $this->get('payment_manager')->calculateRefund($user),
			'levels' => $levels,
			'concepturl' => $this->generateUrl('bm2_site_default_paymentconcept'),
			'creators' => $this->fetchPatreon(),
			'form'=> $form->createView()
		]);
	}

   /**
     * @Route("/patreon/update", name="maf_patreon_update")
     */
	public function patreonUpdateAction(Request $request) {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		$user = $this->getUser();
		$em = $this->getDoctrine()->getManager();
		$now = new \DateTime('now');
		$threeWeeks = $now->sub(new \DateInterval("P21D"));
		$patreons = $user->getPatronizing();
		foreach ($patreons as $patron) {
			if ($patron->getLastUpdate() < $threeWeeks) {
				$creator = $patron->getCreator();
				$poa = new POA($creator->getClientId(), $creator->getClientSecret());
				$tokens = $poa->refresh_token($patron->getRefreshToken(), null);
				$patron->setAccessToken($tokens['access_token']);
				$patron->setRefreshToken($tokens['refresh_token']);
				$patron->setLastUpdate($now);
			}
			$papi = new PAPI($patron->getAccessToken());
			$member = $papi->fetch_user();
			$this->addFlash('notice', print_r($member));
			$patron->setStatus($member['included'][0]['attributes']['patron_status']);
			$patron->setCurrentAmount($entitlement = $member['included'][0]['attributes']['currently_entitled_amount_cents']);
			$em->flush();
			return $this->redirectToRoute('bm2_account');
		}
	}

   /**
     * @Route("/patreon/{creator}", name="maf_patreon", requirements={"creator"="[A-Za-z]+"})
     */
	public function patreonAction(Request $request, $creator) {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		$user = $this->getUser();
		$em = $this->getDoctrine()->getManager();

		$code = $request->query->get('code');
		$creator = $this->fetchPatreon($creator);
		if (isset($code) && !empty($code)) {
			$auth = new POA($creator->getClientId(), $creator->getClientSecret());
			$tokens = $auth->get_tokens($code, $creator->getReturnUri());
			echo $creator->getId();
			$patron = $em->getRepository('BM2SiteBundle:Patron')->findOneBy(["user"=>$user, "creator"=>$creator]);
			if (!$patron) {
				$patron = new Patron();
				$em->persist($patron);
				$patron->setUser($user);
				$patron->setCreator($creator);
			}
			$patron->setAccessToken($tokens['access_token']);
			$patron->setRefreshToken($tokens['refresh_token']);
			$patron->setLastUpdate(new \DateTime('now'));
			$papi = new PAPI($tokens['access_token']);
			$member = $papi->fetch_user();
			$patron->setStatus($member['included'][0]['attributes']['patron_status']);
			$patron->setCurrentAmount($entitlement = $member['included'][0]['attributes']['currently_entitled_amount_cents']);
			$em->flush();
			$this->addFlash('notice', 'account.patronizing');
			return $this->redirectToRoute('bm2_account');
		} else {
			$this->addFlash('notice', 'account.patronfailure');
			return $this->redirectToRoute('bm2_site_payment_subscription');
		}
	}

   /**
     * @Route("/culture")
     */
	public function cultureAction(Request $request) {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		$em = $this->getDoctrine()->getManager();
		$allcultures = $em->createQuery('SELECT c FROM BM2SiteBundle:Culture c INDEX BY c.id')->getResult();
		$nc = $em->createQuery('SELECT c.id as id, count(n.id) as amount FROM BM2SiteBundle:NameList n JOIN n.culture c GROUP BY c.id')->getResult();
		$namescount = array();
		foreach ($nc as $ncx) {
			$namescount[$ncx['id']] = $ncx['amount'];
		}

		$form = $this->createForm(new CultureType($this->getUser(), false));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			$buying = $data['culture'];
			$total = 0;
			foreach ($buying as $buy) {
				$total += $buy->getCost();
			}
			if ($total > $this->getUser()->getCredits()) {
				$form->addError(new FormError($this->get('translator')->trans("account.culture.notenoughcredits")));
			} else {
				foreach ($buying as $buy) {
					// TODO: error handling here?
					$this->get('payment_manager')->spend($this->getUser(), "culture pack", $buy->getCost());
					$this->getUser()->getCultures()->add($buy);
				}
				$em->flush();

				return $this->render('Payment/culture.html.twig', [
					'bought'=>$buying,
					'namescount'=>$namescount
				]);
			}
		}

		return $this->render('Payment/culture.html.twig', [
			'cultures'=>$allcultures,
			'namescount'=>$namescount,
			'form'=>$form->createView()
		]);
	}
   /**
     * @Route("/gift")
     */
	public function giftAction(Request $request) {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		$user = $this->getUser();

		$form = $this->createForm(new GiftType($this->giftchoices, false));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$value = $this->giftchoices[$data['credits']];

			$em = $this->getDoctrine()->getManager();
			$target = $em->getRepository('BM2SiteBundle:User')->findOneByEmail($data['email']);
			if (!$target) {
				sleep(1); // to make brute-forcing e-mail addresses a bit slower
				return array('error'=>'notarget');
			}
			if ($target == $user) {
				return array('error'=>'self');
			}

			$code = $this->get('payment_manager')->createCode($value, 0, $data['email'], $user);
			$this->get('payment_manager')->spend($user, "gift", $value);

			$em->flush();

			$text = $this->get('translator')->trans('account.gift.mail.body', array("%credits%"=>$value, "%code%"=>$code->getCode(), "%message%"=>strip_tags($data['message'])));
			$message = \Swift_Message::newInstance()
				->setSubject($this->get('translator')->trans('account.gift.mail.subject', array()))
				->setFrom(array('mafserver@lemuriacommunity.org' => $this->get('translator')->trans('mail.sender', array(), "communication")))
				->setTo($data['email'])
				->setCc($user->getEmail())
				->setBody($text, 'text/html')
				->setMaxLineLength(1000)
			;
			$numSent = $this->get('mailer')->send($message);
			$this->get('logger')->info("sent gift: ($numSent) from ".$user->getId()." to ".$data['email']." for $value credits");

			return $this->render('Payment/gift.html.twig', [
				'success'=>true, 'credits'=>$value
			]);

		}

		return $this->render('Payment/gift.html.twig', [
			'form'=>$form->createView()
		]);
	}
   /**
     * @Route("/invite")
     */
	public function inviteAction(Request $request) {
		if ($this->get('security.authorization_checker')->isGranted('ROLE_BANNED_MULTI')) {
			throw new AccessDeniedException('error.banned.multi');
		}
		$user = $this->getUser();


		$form = $this->createForm(new GiftType($this->giftchoices, true));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$value = $this->giftchoices[$data['credits']];

			$code = $this->get('payment_manager')->createCode($value, 0, $data['email'], $user);
			$this->get('payment_manager')->spend($user, "gift", $value);

			$this->getDoctrine()->getManager()->flush();

			$text = $this->get('translator')->trans('account.invite.mail.body', array("%credits%"=>$value, "%code%"=>$code->getCode(), "%message%"=>strip_tags($data['message'])));
			$message = \Swift_Message::newInstance()
				->setSubject($this->get('translator')->trans('account.invite.mail.subject', array()))
				->setFrom(array('mafserver@lemuriacommunity.org' => $this->get('translator')->trans('mail.sender', array(), "communication")))
				->setTo($data['email'])
				->setCc($user->getEmail())
				->setBody($text, 'text/html')
				->setMaxLineLength(1000)
			;
			$numSent = $this->get('mailer')->send($message);
			$this->get('logger')->info("sent friend invite: ($numSent) from ".$user->getId()." to ".$data['email']." for $value credits");

			return $this->render('Payment/invite.html.twig', [
				'success'=>true, 'credits'=>$value
			]);
		}

		return $this->render('Payment/invite.html.twig', [
			'form'=>$form->createView()
		]);
	}

}
