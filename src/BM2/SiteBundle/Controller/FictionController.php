<?php

namespace BM2\SiteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;


// FIXME: the / route still doesn't seem to work :-(


/**
 * @Route("/fiction")
 * @Route("/", requirements={"_host"="fiction.mightandfealty.com"})
 */
class FictionController extends Controller {

	private $pages = array(
		'creation',
		'firstones',
		'fall',
		'lendan',
		'geas',
	);

	/**
	  * @Route("/{page}", name="bm2_fiction", defaults={"page"="index"})
	  */
	public function indexAction($page) {

		return $this->render('Fiction/index.html.twig', [
			"simple"=>true, "page"=>$page, "allpages"=>$this->pages
		]);
	}

	/**
	  * @Route("/content/{page}", name="bm2_fiction_content")
	  */
	public function contentAction($page) {
		$pr = $this->get('pagereader');
		$locale = $this->getRequest()->getLocale();

		$content = $pr->getPage('fiction', $page, $locale);

		return $this->render('Fiction/content.html.twig', [
			'title' => $page,
			'content'=>$content
		]);
	}

}
