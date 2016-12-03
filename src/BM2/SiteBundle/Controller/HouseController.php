<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Action;
use BM2\SiteBundle\Entity\Character;

# use BM2\SiteBundle\Form\CharacterBackgroundType;
# use BM2\SiteBundle\Form\CharacterPlacementType;
# use BM2\SiteBundle\Form\CharacterRatingType;
# use BM2\SiteBundle\Form\EntourageManageType;
# use BM2\SiteBundle\Form\SoldiersManageType;
# use BM2\SiteBundle\Form\InteractionType;

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
 * @Route("/house")
 */
class HouseController extends Controller {

	private $house;


