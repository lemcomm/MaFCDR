<?php

namespace Calitarus\MessagingBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use Calitarus\MessagingBundle\Entity\Message;

use Calitarus\MessagingBundle\Form\NewConversationType;
use Calitarus\MessagingBundle\Form\MessageReplyType;


use BM2\SiteBundle\Entity\Realm;



/**
 * @Route("/write")
 */
class WriteController extends Controller {

	/**
		* @Route("/new_conversation", name="cmsg_new_conversation")
		* @Route("/new_conversation/{realm}", name="cmsg_new_conversation_in_group")
		* @Template
		*/
	public function newconversationAction(Request $request, Realm $realm=null) {
		$user = $this->get('message_manager')->getCurrentUser();
		$character = $this->get('appstate')->getCharacter();

		if ($realm && !$character->findRealms()->contains($realm)) {
			$realm = null;
		}

		if ($realm) {
			$contacts = null;
			$distance = null;
			$settlement = null;
		} else {
			if ($character->getAvailableEntourageOfType("herald")->isEmpty()) {
				$distance = $this->get('geography')->calculateInteractionDistance($character);
			} else {
				$distance = $this->get('geography')->calculateSpottingDistance($character);
			}
			$this->get('dispatcher')->setCharacter($character);
			$settlement = $this->get('dispatcher')->getActionableSettlement();
			$contacts = $this->get('message_manager')->getContactsList();	
		}

		$form = $this->createForm(new NewConversationType($contacts, $distance, $character, $settlement, $realm));

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			$recipients = new ArrayCollection;
			if (isset($data['nearby'])) foreach ($data['nearby'] as $rec) {
				$r = $this->get('message_manager')->getMsgUser($rec);
				if (!$recipients->contains($r)) {
					$recipients->add($r);
				}
			}
			if (isset($data['contacts'])) foreach ($data['contacts'] as $rec) {
				$r = $this->get('message_manager')->getMsgUser($rec);
				if (!$recipients->contains($r)) {
					$recipients->add($r);
				}
			}
			if (isset($data['owner'])) foreach ($data['owner'] as $rec) {
				$r = $this->get('message_manager')->getMsgUser($rec);
				if (!$recipients->contains($r)) {
					$recipients->add($r);
				}
			}
/*
	FIXME: parent is disabled until fixed in NewConversationType
			$this->get('message_manager')->newConversation($user, $recipients, $data['topic'], $data['content'], $data['parent'], $realm);
*/
			$this->get('message_manager')->newConversation($user, $recipients, $data['topic'], $data['content'], null, $realm);
			$this->getDoctrine()->getManager()->flush();
			return $this->redirectToRoute('cmsg_summary');
		}

		return array(
			'form' => $form->createView(),
			'realm' => $realm
		);
	}


	/**
		* @Route("/reply", name="cmsg_reply")
		* @Template
		*/
	public function replyAction(Request $request) {
		$user = $this->get('message_manager')->getCurrentUser();

		$form = $this->createForm(new MessageReplyType());

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$em = $this->getDoctrine()->getManager();
			$source = $em->getRepository('MsgBundle:Message')->find($data['reply_to']);

			// check that we are a participant - TODO: and have write permissions 
			if ($data['conversation']) {
				$meta = $em->getRepository('MsgBundle:ConversationMetadata')->find($data['conversation']);
			} else if ($source) {
				$meta = $source->getConversation()->findMeta($user);
			}
			if (!$meta || $meta->getUser() != $user) {
				throw new AccessDeniedHttpException($this->get('translator')->trans('error.conversation.noaccess', array(), "MsgBundle"));
			}

			if ($source) {
				if ($data['conversation']>0) {
					// reply
					$message = $this->get('message_manager')->writeReply($source, $user, $data['content']);
				} else {
					// split-reply
					if (isset($data['topic']) && $data['topic']!="") {
						$topic = $data['topic'];
					} else {
						// no topic given so we increment the last one
						preg_match("/(.*) ([0-9]+)$/", $source->getConversation()->getTopic(), $matches);
						if ($matches) {
							$nr = (int)$matches[2] + 1;
							$topic = $matches[1]." $nr";
						} else {
							$topic = $source->getConversation()->getTopic()." 2";
						}

					}
					// create the split
					$newmeta = $this->get('message_manager')->writeSplit($source, $user, $topic, $data['content']);
					return array('plain' => $this->get('router')->generate('cmsg_conversation', array('meta'=>$newmeta->getId())));
				}
			} else {
				$message = $this->get('message_manager')->addMessage($meta->getConversation(), $user, $data['content']);
			}
			$em->flush();
			return array('message' => $message, 'user'=>$user);
		}

		return array(
			'form' => $form->createView()
		);
	}

}
