<?php

namespace Calitarus\MessagingBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use Calitarus\MessagingBundle\Entity\ConversationMetadata;
use Calitarus\MessagingBundle\Entity\MessageMetadata;


/**
 * @Route("/read")
 */
class ReadController extends Controller {

	/**
		* @Route("/", name="cmsg_index")
		* @Template
		*/
	public function indexAction() {
		$metas = $this->get('message_manager')->getConversationsMeta(null, true);

		return array('conversations' => $metas);
	}

	/**
		* @Route("/summary", name="cmsg_summary")
		* @Template
		*/
	public function summaryAction() {
		$user = $this->get('message_manager')->getCurrentUser();

		$total = 0;
		$new = array('messages' => 0, 'conversations' => 0);
		foreach ($user->getConversationsMetadata() as $meta) {
			$total++;
			if ($meta->getUnread() > 0) {
				$new['messages'] += $meta->getUnread();
				$new['conversations']++;
			}
		}

		return array(
			'total' => $total,
			'new' => $new,
			'unread' => $this->get('message_manager')->getUnreadMessages($user),
			'flagged' => $this->get('message_manager')->countFlaggedMessages($user),
			'local_news' => $this->get('news_manager')->getLocalList($user->getAppUser())
		);
	}


	/**
		* @Route("/unread", name="cmsg_unread")
		* @Template
		*/
	public function unreadAction() {
		$user = $this->get('message_manager')->getCurrentUser();

		return array('unread' => $this->get('message_manager')->getUnreadMessages($user));
	}

	/**
		* @Route("/flagged", name="cmsg_flagged")
		* @Template
		*/
	public function flaggedAction() {
		$user = $this->get('message_manager')->getCurrentUser();

		return array(
			'user' => $user,
			'flagged' => $this->get('message_manager')->getFlaggedMessages($user)
		);
	}

	/**
		* @Route("/contacts", name="cmsg_contacts")
		* @Template
		*/
	public function contactsAction() {
		return array('contacts' => $this->get('message_manager')->getContactsList());
	}


	/**
		* @Route("/conversation/{meta}", name="cmsg_conversation", requirements={"meta"="\d+"})
		* @Template
		*/
	public function conversationAction(ConversationMetadata $meta) {
		$user = $this->get('message_manager')->getCurrentUser();

		if ($meta->getUser() != $user) {
			throw new AccessDeniedHttpException($this->get('translator')->trans('error.conversation.noaccess', array(), "MsgBundle"));
		}

		$last = $meta->getLastRead();
		if ($last==null) {
			// if this is null, we've never read this conversation, so everything is new
			$last = new \DateTime('2014-01-01');
		}
		$data = $this->get('message_manager')->getConversation($meta);

		// flush to update our read status
		$this->getDoctrine()->getManager()->flush();

		$veryold = new \DateTime('now');
		$veryold->sub(new \DateInterval("P7D")); // TODO: make this user-configurable

		return array(
			'meta' => $meta,
			'last' => $last,
			'data' => $data,
			'veryold' => $veryold,
			'unread' => $this->get('message_manager')->getUnreadMessages($user),
		);
	}


	/**
		* @Route("/related/{meta}", name="cmsg_related", requirements={"meta"="\d+"})
		* @Template
		*/
	public function relatedAction(ConversationMetadata $meta, Request $request) {
		$user = $this->get('message_manager')->getCurrentUser();

		if ($meta->getUser() != $user) {
			throw new AccessDeniedHttpException($this->get('translator')->trans('error.conversation.noaccess', array(), "MsgBundle"));
		}

		$id = $request->query->get('id');
		$type = $request->query->get('type');

		$source = $this->getDoctrine()->getManager()->getRepository('MsgBundle:Message')->find($id);
		$messages = new ArrayCollection;
		if ($type=='source') {
			$related = $source->getRelatedToMe();
			foreach ($related as $rel) {
				$messages->add($rel->getSource());
			}
		} else {
			$related = $source->getRelatedMessages();
			foreach ($related as $rel) {
				$messages->add($rel->getTarget());
			}
		}

		// TODO: modify the counter on the conversation now that we're showing the messages... - but for that we might have to know not only how many, but also which messages are unread...

		return array('user' => $user, 'messages' => $messages, 'hide' => $source);
	}


	/**
		* @Route("/flags", name="cmsg_flags")
		* @Template
		*/
	public function flagsAction(Request $request) {
		$user = $this->get('message_manager')->getCurrentUser();
		$em = $this->getDoctrine()->getManager();

		$message = $em->getRepository('MsgBundle:Message')->find($request->request->get('message'));
		if (!$message) {
			return new Response("message not found");
		}
		$flag = $em->getRepository('MsgBundle:Flag')->findOneByName($request->request->get('flag'));
		if (!$flag) {
			return new Response("flag not found");
		}

		$meta = $message->findMeta($user);
		if (!$meta) {
			$meta = new MessageMetadata;
			$meta->setMessage($message);
			$meta->setUser($user);
			$meta->setScore(0);
			$meta->setTags(array());
			$em->persist($meta);
		}

		if ($meta->getFlags()->contains($flag)) {
			$meta->removeFlag($flag);
			$em->flush();
			return new Response("removed");
		} else {
			$meta->addFlag($flag);
			$em->flush();
			return new Response("added");
		}
	}
}
