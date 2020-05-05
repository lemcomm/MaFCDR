<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Settlement;
use BM2\SiteBundle\Form\SettlementPermissionsSetType;
use BM2\SiteBundle\Form\DescriptionNewType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;


/**
 * @Route("/settlement")
 */
class SettlementController extends Controller {

	private $slice_size = 500;

	/**
	  * @Route("/{id}", name="bm2_settlement", requirements={"id"="\d+"})
	  * @Template("BM2SiteBundle:Settlement:settlement.html.twig")
	  */
	public function indexAction(Settlement $id) {
		$em = $this->getDoctrine()->getManager();
		$settlement = $id; // we use $id because it's hardcoded in the linkhelper

		// check if we should be able to see details
		$character = $this->get('appstate')->getCharacter(false);
		if ($character instanceof Character) {
			$heralds = $character->getAvailableEntourageOfType('Herald')->count();
		} else {
			$heralds = 0;
			$character = NULL; //Override Appstate's return so we don't need to tinker with the rest of this function.
		}
		$details = $this->get('interactions')->characterViewDetails($character, $settlement);
		if (isset($details['startme'])) {
			// still in start mode
			$form_map = $this->createFormBuilder()->add('settlement_id', 'hidden', array(
				'constraints' => array() // TODO: constrained to available settlements
			))->getForm();
			$details['startme'] = $form_map->createView();
		}

		// FIXME: shouldn't this use geodata?
		$query = $em->createQuery('SELECT s.id, s.name, ST_Distance(y.center, x.center) AS distance, ST_Azimuth(y.center, x.center) AS direction
			FROM BM2SiteBundle:Settlement s JOIN s.geo_data x, BM2SiteBundle:GeoData y WHERE y=:here AND ST_Touches(x.poly, y.poly)=true');
		$query->setParameter('here', $settlement);
		$neighbours = $query->getArrayResult();

		if ($details['spy'] || $settlement->getOwner() == $character) {
			$militia = $settlement->getActiveMilitiaByType();
		} else {
			$militia = null;
		}

		// FIXME: better: some trend analysis
		$query = $em->createQuery('SELECT s.population as pop FROM BM2SiteBundle:StatisticSettlement s WHERE s.settlement = :here ORDER BY s.cycle DESC');
		$query->setParameter('here', $settlement);
		$query->setMaxResults(3);
		$data = $query->getArrayResult();
		if (isset($data[2])) {
			$popchange = $data[0]['pop'] - $data[2]['pop'];
		} else {
			$popchange = 0;
		}

		$corruption = $this->get('economy')->calculateCorruption($settlement);
		if ($character != $settlement->getOwner()) {
			// rounding this to full percents to fuzz it a bit, to prevent people from understanding which characters belong to the same player by corruption values
			$corruption = round($corruption*100)/100;
		}

		$economy = array();
		$all = $em->getRepository('BM2SiteBundle:ResourceType')->findAll();
		foreach ($all as $resource) {
			$local = $settlement->findResource($resource);
			if ($local) {
				$base = $local->getAmount();
				$storage = $local->getStorage();
			} else {
				$base = 0;
				$storage = 0;
			}

			if ($details['spot'] && ($details['prospector'] || $settlement->getOwner() == $character)) {
				// TODO: we should fuzz corruption a bit to prevent people spotting same users by comparing corruption
				$full_demand = $this->get('economy')->ResourceDemand($settlement, $resource, true);
				$demand = 0;
				foreach ($full_demand as $key=>$value) {
					$demand += $value;
				}
				$production = $this->get('economy')->ResourceProduction($settlement, $resource);
				$base_production = $this->get('economy')->ResourceProduction($settlement, $resource, true);
			} else {
				$full_demand = array('base'=>0, 'corruption'=>0, 'operation'=>0, 'construction'=>0);
				$demand = $production = $base_production = 0;
			}

			$economy[] = array(
				'name' => $resource->getName(),
				'base' => $base,
				'storage' => $storage,
				'base_production' => $base_production,
				'total_production' => $production,
				'tradebalance' => $this->get('economy')->TradeBalance($settlement, $resource),
				'base_demand' => $full_demand['base'],
				'corruption' => $full_demand['corruption'],
				'total_demand' => $demand,
				'building_prod' => $production - $base_production,
				'building_demand' => $full_demand['operation'],
				'building_construction' => $full_demand['construction']
			);
			if ($resource->getName()=="food") {
				if ($demand>0) {
					$FoodSupply = $this->get('economy')->ResourceAvailable($settlement, $resource) / $demand;
				} else {
					$FoodSupply = 1.0;
				}
			}
		}

		return array(
			'settlement' => $settlement,
			'familiarity' => $character?$this->get('geography')->findRegionFamiliarityLevel($character, $settlement->getGeoData()):false,
			'details' => $details,
			'popchange' => $popchange,
			'foodsupply' => $FoodSupply,
			'economy' => $economy,
			'corruption' => $corruption,
			'area' => $this->get('geography')->calculateArea($settlement->getGeoData()),
			'density' => $this->get('geography')->calculatePopulationDensity($settlement),
			'regionpoly'=> $this->get('geography')->findRegionPolygon($settlement),
			'neighbours' => $neighbours,
			'militia' => $militia,
			'recruits' => $settlement->getRecruits()->count(),
			'security' => round(($this->get('economy')->EconomicSecurity($settlement)-1.0)*16),
			'heralds' => $heralds
		);
	}

	/**
	  * @Route("/{id}/permissions", requirements={"id"="\d+"})
	  * @Template
	  */
	public function permissionsAction($id, Request $request) {
		$character = $this->get('dispatcher')->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
		$settlement = $em->getRepository('BM2SiteBundle:Settlement')->find($id);
		if (!$settlement) {
			throw $this->createNotFoundException('error.notfound.settlement');
		}
		if ($settlement->getOwner() !== $character) {
			throw $this->createNotFoundException('error.noaccess.settlement');
		}

		$original_permissions = clone $settlement->getPermissions();

		$form = $this->createForm(new SettlementPermissionsSetType($character, $this->getDoctrine()->getManager()), $settlement);

		// FIXME: right now, nothing happens if we disallow thralls while having some
		//			 something should happen - set them free? most should vanish, but some stay as peasants?
		//			 but do we want large numbers of people to simply disappear? where will they go?

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			foreach ($settlement->getPermissions() as $permission) {
				$permission->setValueRemaining($permission->getValue());
				if (!$permission->getId()) {
					$em->persist($permission);
				}
			}
			foreach ($original_permissions as $orig) {
				if (!$settlement->getPermissions()->contains($orig)) {
					$em->remove($orig);
				}
			}
			$em->flush();
			$this->addFlash('notice', $this->get('translator')->trans('control.permissions.success', array(), 'actions'));
			return $this->redirect($request->getUri());
		}

		return array(
			'settlement' => $settlement,
			'permissions' => $em->getRepository('BM2SiteBundle:Permission')->findByClass('settlement'),
			'form' => $form->createView()
		);
	}

	/**
	  * @Route("/{id}/quests", requirements={"id"="\d+"})
	  * @Template
	  */
	public function questsAction($id, Request $request) {
		$character = $this->get('dispatcher')->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
		$settlement = $em->getRepository('BM2SiteBundle:Settlement')->find($id);
		if (!$settlement) {
			throw $this->createNotFoundException('error.notfound.settlement');
		}
		if ($settlement->getOwner() !== $character) {
			throw $this->createNotFoundException('error.noaccess.settlement');
		}
		return array('settlement'=>$settlement, 'quests'=>$settlement->getQuests());
	}

	/**
	  * @Route("/{id}/description", requirements={"id"="\d+"})
	  * @Template
	  */
	public function descriptionAction($id, Request $request) {
		$character = $this->get('dispatcher')->gateway();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}
		$em = $this->getDoctrine()->getManager();
		$settlement = $em->getRepository('BM2SiteBundle:Settlement')->find($id);
		if (!$settlement) {
			throw $this->createNotFoundException('error.notfound.settlement');
		}
		if ($settlement->getOwner() !== $character) {
			throw $this->createNotFoundException('error.noaccess.settlement');
		}
		$desc = $settlement->getDescription();
		if ($desc) {
			$text = $desc->getText();
		} else {
			$text = null;
		}
		$form = $this->createForm(new DescriptionNewType($text));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			if ($text != $data['text']) {
				$desc = $this->get('description_manager')->newDescription($settlement, $data['text'], $character);
			}
			$this->getDoctrine()->getManager()->flush();
			$this->addFlash('notice', $this->get('translator')->trans('control.description.success', array(), 'actions'));
		}
		return array('settlement'=>$settlement, 'form'=>$form->createView());
	}

}
