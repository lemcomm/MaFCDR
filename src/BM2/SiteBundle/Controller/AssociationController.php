<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Association;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\GameRequest;

use BM2\SiteBundle\Form\AreYouSureType;
use BM2\SiteBundle\Form\DescriptionNewType;

use BM2\SiteBundle\Service\DescriptionManager;
use BM2\SiteBundle\Service\GameRequestManager;
use BM2\SiteBundle\Service\Geography;
use BM2\SiteBundle\Service\History;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * @Route("/assoc")
 */
class AssociationController extends Controller {

	/**
	  * @Route("/{id}", name="maf_house", requirements={"id"="\d+"})
	  */

	public function viewAction(Association $id) {
		$assoc = $id;
		$details = false;
		$head = false;
		$char = $this->get('appstate')->getCharacter(false, true, true);
		if ($char instanceof Character) {
			foreach ($char->getAssociationMembership() as $member) {
				if ($member->getAssocation() === $assoc) {
					$details = true;
					if ($member->getRank()->isHead()) {
						$head = true;
					}
					break;
				}

			}
		}
		$this->addFlash('notice', "This isn't ready yet, come back later you silly person!");
		return $this->redirectToRoute('bm2_homepage');

		return $this->render('Assoc/view.html.twig', [
			'assoc' => $assoc,
			'details' => $details,
			'head' => $head
		]);
	}

}
