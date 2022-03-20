<?php

namespace BM2\SiteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * @Route("/data")
 */
class DataController extends Controller {

	/**
     * @Route("/characters")
     */
	public function charactersAction(Request $request) {
		$term = $request->query->get("term");
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT c.id, c.name as value FROM BM2SiteBundle:Character c WHERE c.alive=true AND c.slumbering=false AND LOWER(c.name) LIKE :term ORDER BY c.name ASC');
		$query->setParameter('term', '%'.strtolower($term).'%');
		$result = $query->getArrayResult();

		$response = new Response(json_encode($result));
		$response->headers->set('Content-Type', 'application/json');
		return $response;
	}

	/**
     * @Route("/realms")
     */
	public function realmsAction(Request $request) {
		$term = $request->query->get("term");
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT r.id, r.name as value FROM BM2SiteBundle:Realm r WHERE LOWER(r.name) LIKE :term OR LOWER(r.formal_name) LIKE :term ORDER BY r.name ASC');
		$query->setParameter('term', '%'.strtolower($term).'%');
		$result = $query->getArrayResult();

		$response = new Response(json_encode($result));
		$response->headers->set('Content-Type', 'application/json');
		return $response;
	}

	/**
     * @Route("/settlements")
     */
	public function settlementsAction(Request $request) {
		$term = $request->query->get("term");
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT s.id, s.name as value, ST_X(g.center) as x, ST_Y(g.center) as y, r.name as label FROM BM2SiteBundle:Settlement s JOIN s.geo_data g LEFT JOIN s.realm r WHERE LOWER(s.name) LIKE :term ORDER BY s.name ASC');
		$query->setParameter('term', '%'.strtolower($term).'%');
		$result = $query->getArrayResult();

		$response = new Response(json_encode($result));
		$response->headers->set('Content-Type', 'application/json');
		return $response;
	}

	/**
     * @Route("/assocs")
     */
	public function assocsAction(Request $request) {
		$term = $request->query->get("term");
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT a.id, a.name as value FROM BM2SiteBundle:Association a WHERE LOWER(a.name) LIKE :term OR LOWER(a.formal_name) LIKE :term ORDER BY a.name ASC');
		$query->setParameter('term', '%'.strtolower($term).'%');
		$result = $query->getArrayResult();

		$response = new Response(json_encode($result));
		$response->headers->set('Content-Type', 'application/json');
		return $response;
	}

	/**
     * @Route("/places")
     */
	public function placesAction(Request $request) {
		$term = $request->query->get("term");
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT p.id, p.name as value FROM BM2SiteBundle:Place p WHERE LOWER(p.name) LIKE :term OR LOWER(p.formal_name) LIKE :term ORDER BY p.name ASC');
		$query->setParameter('term', '%'.strtolower($term).'%');
		$result = $query->getArrayResult();

		$response = new Response(json_encode($result));
		$response->headers->set('Content-Type', 'application/json');
		return $response;
	}

	/**
     * @Route("/houses")
     */
	public function housesAction(Request $request) {
		$term = $request->query->get("term");
		$em = $this->getDoctrine()->getManager();
		$query = $em->createQuery('SELECT h.id, h.name as value FROM BM2SiteBundle:House h WHERE LOWER(h.name) LIKE :term ORDER BY h.name ASC');
		$query->setParameter('term', '%'.strtolower($term).'%');
		$result = $query->getArrayResult();

		$response = new Response(json_encode($result));
		$response->headers->set('Content-Type', 'application/json');
		return $response;
	}

}
