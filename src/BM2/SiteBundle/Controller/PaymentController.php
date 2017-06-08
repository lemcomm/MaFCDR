<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Form\CultureType;
use BM2\SiteBundle\Form\GiftType;
use BM2\SiteBundle\Form\SubscriptionType;
#use Calitarus\BitPayBundle\Form\DonationType;
#use Calitarus\BitPayBundle\Form\ItemType;
use PayPal;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @Route("/payment")
 */
class PaymentController extends Controller {

	private $giftchoices = array(100, 200, 300, 400, 500, 600, 800, 1000, 1200, 1500, 2000, 2500);

	// FIXME: the secrets, etc. should probably not be in here, but in a safe place

	// BitPay
	/*private $bitpay_apikey = "hPEcH5b5ZqLdd32pct1isv5AsKvA5YxDFjB9938";
	private $bitpay_donation = "XdezXZUktL9Y8zzTDGcM4+OCYk88WECctXkkCg/f5yjAZxotXlJTKNVb3Ax4N/3KT5Ks+C+3zsJpbOh6xnOAzQBrobm78R1JB1PXNv5d6EkVHcEFIA0qARfjH9sQGfbnfFykiZGzHs7jJ/XTLNqbzrbNBbI4FxoWxT01U4IQ/jJ5jZbvoCWmj6Rb+VhgwqHY+F4W1Bbo5KHfBcoexhm3z1LRtLSH34mSZ4IZB3h70/1daRL5E1jlG2CHCOQ6Q0mnSGNRtJ/H5kWOQa3HIdKIXKCxvsCvzZvBR7iJQPE6rMZ4//fh450RBSxGHIlCiFb/";
	private $bitpay_items = array(
		'5 €uro' => '4YjgZMrnTDenLetcpiyX1W',
		'10 €uro' => '5JjtgbL3FHJTrYPe4Fmh8y',
		'20 €uro' => 'QU6p9DPJTeMoCL5fvtkkos',
		'30 €uro' => 'L7PhvLK8tB18RnQoCSXvQt',
		'50 €uro' => 'UTu56tAH1WUkeHe8WKJzQU',
	);*/


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
     * @Template("BM2SiteBundle:Payment:payment.html.twig")
     */
	public function paymentAction(Request $request) {
		$user = $this->getUser();

		/*$bitpay = array();
		foreach ($this->bitpay_items as $key=>$code) {
			$bitpay[$key] = $this->createForm(new ItemType($code, $user, $key))->createView();
		}*/

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

		/* Commenting this out until we add bitpay back in, if ever.
		return array(
			'form' => $form->createView(),
			'redeemed' => $redeemed,
			'bitpay' => $bitpay
		); 
		*/
		return array(
			'form' => $form->createView(),
			'redeemed' => $redeemed
		);
	}


	/**
	  * @Route("/paypal/{amount}", name="bm2_paypal", requirements={"amount"="\d+"})
	  */
	public function paypalAction($amount, Request $request) {
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
	  * @Route("/bitpay")
	  */
	public function bitpayAction(Request $request) {
		$data = $this->get('bitpay')->bpVerifyNotification($request, $this->bitpay_apikey);

		return new Response();
	}

	/**
	  * @Route("/testpayment")
	  * @Template
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
     * @Template
     */
	public function creditsAction() {
		$user = $this->getUser();

		return array(
			'myfee' =>			$this->get('payment_manager')->calculateUserFee($user),
			'levels' =>			$this->get('payment_manager')->getPaymentLevels(),
			'concepturl' =>	$this->generateUrl('bm2_site_default_paymentconcept')
		);
	}

   /**
     * @Route("/subscription")
     * @Template
     */
	public function subscriptionAction(Request $request) {
		$user = $this->getUser();
		$levels = $this->get('payment_manager')->getPaymentLevels();

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

		return array(
			'myfee' =>			$this->get('payment_manager')->calculateUserFee($user),
			'refund' => 		$this->get('payment_manager')->calculateRefund($user),
			'levels' =>			$levels,
			'concepturl' =>	$this->generateUrl('bm2_site_default_paymentconcept'),
			'form'=>				$form->createView()
		);
	}

   /**
     * @Route("/culture")
     * @Template
     */
	public function cultureAction(Request $request) {
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
				return array(
					'bought'=>$buying,
					'namescount'=>$namescount
				);
			}
		}
		return array(
			'cultures'=>$allcultures,
			'namescount'=>$namescount,
			'form'=>$form->createView()
		);
	}
   /**
     * @Route("/gift")
     * @Template
     */
	public function giftAction(Request $request) {
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

			return array('success'=>true, 'credits'=>$value);

		}

		return array('form'=>$form->createView());
	}
   /**
     * @Route("/invite")
     * @Template
     */
	public function inviteAction(Request $request) {
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

			return array('success'=>true, 'credits'=>$value);
		}

		return array('form'=>$form->createView());
	}

}
