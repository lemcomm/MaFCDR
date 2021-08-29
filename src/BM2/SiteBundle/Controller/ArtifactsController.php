<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Artifact;
use BM2\SiteBundle\Service\History;
use BM2\SiteBundle\Form\CharacterSelectType;
use BM2\SiteBundle\Form\InteractionType;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/artifacts")
 */
class ArtifactsController extends Controller {

	/**
	  * @Route("/owned")
	  */
	public function ownedAction() {
		$user = $this->getUser();

		return $this->render('Artifacts/owned.html.twig', [
			'artifacts'=>$user->getArtifacts(),
		]);
	}

	/**
	  * @Route("/create")
	  */
	public function createAction(Request $request) {
		$user = $this->getUser();

		if ($user->getArtifacts()->count() < $user->getArtifactsLimit()) {
			$form = $this->createFormBuilder()
				->add('name', 'text', array(
					'required'=>true,
					'label'=>'artifact.create.name'
					))
				->add('description', 'textarea', array(
					'required'=>true,
					'label'=>'artifact.create.description'
					))
				->add('submit', 'submit', array('label'=>'artifact.create.submit'))
				->getForm();
			$form->handleRequest($request);
			if ($form->isValid()) {
				$data = $form->getData();
				$name = trim($data['name']);
				$desc = trim($data['description']);

				if (strlen($name) < 6) {
					$form->addError(new FormError("Your name should be at least 6 characters long."));
					return array('form'=>$form->createView());
				}

				$em = $this->getDoctrine()->getManager();

				// TODO: this might become expensive when we have a lot, as similar_text has a complexity of O(N^3)
				foreach ($em->getRepository('BM2SiteBundle:Artifact')->findAll() as $check) {
					similar_text(strtolower($name), strtolower($check->getName()), $percent);
					if ($percent > 90.0) {
						$form->addError(new FormError("Your name is too similar to an existing name (".$check->getName()."). Please choose a more unique name."));
						return array('form'=>$form->createView());
					}
				}

				$artifact = new Artifact;
				$artifact->setName($name);
				$artifact->setOldDescription($desc);
				$artifact->setCreator($user);
				$em->persist($artifact);

				$this->get('history')->logEvent(
					$artifact,
					'event.artifact.created',
					array(),
					History::MEDIUM, true
				);

				$em->flush();
				return $this->redirectToRoute('bm2_site_artifacts_details', array('id'=>$artifact->getId()));
			}

			return $this->render('Artifacts/create.html.twig', [
				'form'=>$form->createView(),
			]);
		} else {
			return $this->render('Artifacts/create.html.twig', [
				'limit_reached' => false,
			]);
		}
	}

	/**
	  * @Route("/details/{id}", requirements={"id"="\d+"})")
	  */
	public function detailsAction(Artifact $id) {
		return $this->render('Artifacts/details.html.twig', [
			'artifact'=>$id,
		]);
	}

	/**
	  * @Route("/assign/{id}", requirements={"id"="\d+"})")
	  */
	public function assignAction(Artifact $id, Request $request) {
		$user = $this->getUser();
		$artifact = $id;

		if ($artifact->getCreator() != $user) {
			throw new \Exception("Not your artifact.");
		}
		if ($artifact->getOwner()) {
			throw new \Exception("Artifact already has an owner.");
		}

		$characters = array();
		foreach ($user->getCharacters() as $char) {
			if ($char->isAlive()) {
				$characters[] = $char->getId();
			}
		}
		$form = $this->createForm(new CharacterSelectType($characters, 'form.choose', 'choose target', 'assign artifact', 'messages'));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			$artifact->setOwner($data['target']);
			$this->get('history')->logEvent(
				$artifact,
				'event.artifact.assigned',
				array('%link-character%'=>$data['target']->getId()),
				History::MEDIUM, true
			);

			$this->getDoctrine()->getManager()->flush();
			return $this->render('Artifacts/assign.html.twig', [
				'artifact'=>$artifact, 'givento'=>$data['target']
			]);
		}
		return $this->render('Artifacts/assign.html.twig', [
			'artifact'=>$artifact, 'form'=>$form->createView()
		]);
	}

	/**
	  * @Route("/spawn/{id}", requirements={"id"="\d+"})")
	  */
	public function spawnAction(Artifact $id, Request $request) {
		$user = $this->getUser();
		$artifact = $id;

		throw new \Exception("Area spawning is not yet supported.");

		if ($artifact->getCreator() != $user) {
			throw new \Exception("Not your artifact.");
		}
		if ($artifact->getOwner()) {
			throw new \Exception("Artifact already has an owner.");
		}

		$form = $this->createFormBuilder()
			->add('poi', 'entity', array(
				'label'=>'choose area to drop artifact in',
				'placeholder'=>'form.choose',
				'multiple'=>false,
				'expanded'=>false,
				'class'=>'BM2SiteBundle:MapPOI', 'choice_label'=>'name'
				))
			->add('submit', 'submit', array('label'=>'create'))
			->getForm();
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			list($x, $y, $geodata) = $this->get('geography')->findRandomPointInsidePOI($data['poi']);
			if ($geodata) {
				echo "found spot near ".$geodata->getSettlement()->getName();
			} else {
				echo "nothing found";
			}

			$this->get('history')->logEvent(
				$artifact,
				'event.artifact.spawned',
				array('%area%'=>$data['poi']->getName()),
				History::MEDIUM, true
			);

		}
		return $this->render('Artifacts/spawn.html.twig', [
			'artifact'=>$artifact, 'form'=>$form->createView()
		]);
	}


	/**
	  * @Route("/give")
	  */
	public function giveAction(Request $request) {
		$character = $this->get('dispatcher')->gateway('locationGiveArtifactTest');

		$form = $this->createForm(new InteractionType('giveartifact', $this->get('geography')->calculateInteractionDistance($character), $character));
		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$artifact = $data['artifact'];
			$target = $data['target'];
			$em = $this->getDoctrine()->getManager();

			$artifact->setOwner($target);

			$this->get('history')->logEvent(
				$artifact,
				'event.artifact.given',
				array('%link-character-1%'=>$character->getId(), '%link-character-2%'=>$target->getId()),
				History::MEDIUM, true
			);

			$this->get('history')->logEvent(
				$target,
				'event.character.gotartifact',
				array('%link-character%'=>$character->getId(), '%link-artifact%'=>$artifact->getId()),
				History::MEDIUM, true, 20
			);
			$em->flush();
			return $this->render('Artifacts/give.html.twig', [
				'success'=>true, 'artifact'=>$artifact, 'target'=>$target
			]);
		}
		return $this->render('Artifacts/give.html.twig', [
			'form'=>$form->createView()
		]);
	}

}
