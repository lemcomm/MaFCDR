<?php

namespace BM2\SiteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;


/**
 * @Route("/")
 */
class DefaultController extends Controller {

	/**
	  * @Route("/", name="bm2_homepage")
	  * @Template
	  */
	public function indexAction() {
		return array("simple"=>true);
	}

	/**
	  * @Route("/about", name="bm2_about")
	  * @Template
	  */
	public function aboutAction() {
		$pr = $this->get('pagereader');
		$locale = $this->getRequest()->getLocale();

		$intro = $pr->getPage('about', 'introduction', $locale);
		$concept = $pr->getPage('about', 'concept', $locale);
		$gameplay = $pr->getPage('about', 'gameplay', $locale);
		$tech = $pr->getPage('about', 'technology', $locale);

		return array(
			"simple"=>true,
			'intro' => $intro,
			'concept' => $concept,
			'gameplay' => $gameplay,
			'tech' => $tech,
			'levels' => $this->get('payment_manager')->getPaymentLevels(),
			'concepturl' => $this->generateUrl('bm2_site_default_paymentconcept'),
		);
	}

	/**
	  * @Route("/manual/{page}", name="bm2_manual", defaults={"page"="intro"})
	  * @Template
	  */
	public function manualAction($page) {
		$toc = $this->get('pagereader')->getPage('manual', 'toc', $this->getRequest()->getLocale());
		$pagecontent = $this->get('pagereader')->getPage('manual', $page, $this->getRequest()->getLocale());
		return array(
			"page" => $page,
			"toc" => $toc,
			"content" => $pagecontent
		);
	}

	/**
	  * @Route("/vips", name="bm2_vips")
	  * @Template
	  */
	public function vipsAction() {
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT u.display_name, u.vip_status FROM BM2SiteBundle:User u WHERE u.vip_status > 0 ORDER BY u.vip_status DESC, u.display_name');
		$vips = $query->getResult();

		return array("simple"=>true, "vips"=>$vips);
	}


	/**
     * @Route("/contact", name="bm2_contact")
     * @Template
     */
	public function contactAction() {
		return array("simple"=>true);
	}

	/**
     * @Route("/credits", name="bm2_credits")
     * @Template
     */
	public function creditsAction() {
		return array("simple"=>true);
	}

	/**
     * @Route("/terms", name="bm2_terms")
     * @Template
     */
	public function termsAction() {
		return array("simple"=>true, "locale"=>$this->getRequest()->getLocale());
	}

	/**
     * @Route("/paymentconcept")
     * @Template
     */
	public function paymentConceptAction() {
		$pagecontent = $this->get('pagereader')->getPage('about', 'payment', $this->getRequest()->getLocale());

		return array(
			"simple"=>true,
			"content"=>$pagecontent,
			"paylevels"=>$this->get('payment_manager')->getPaymentLevels()
		);
	}


	public function localeRedirectAction($url) {
		if ($url=="-") $url="";
		if (preg_match('/^[a-z]{2}\//', $url)===1) {
			if (substr($url, 0, 2)=='en') {
        		throw $this->createNotFoundException('error.notfound.page');
        	}
			// unsupported locale - default to english - en
			$locale = 'en';
			$url = substr($url,3);
		} else {
			// no locale parameter - use the user's setting, defaulting to browser settings
			if ($user = $this->getUser()) {
				$locale = $user->getLanguage();
			}
			if (!isset($locale) || !$locale) {
				$locale = substr($this->getRequest()->getPreferredLanguage(),0,2);
			}
			if ($locale) {
				$languages = $this->get('appstate')->availableTranslations();
				if (!isset($languages[$locale])) {
					$locale='en';
				}
			} else {
				$locale='en';
			}
		}
		return $this->redirect('/'.$locale.'/'.$url);
	}

}
