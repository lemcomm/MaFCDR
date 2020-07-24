<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Conversation;
use BM2\SiteBundle\Entity\Message;
use BM2\SiteBundle\Form\MessageReplyType;

use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @Route("/conv")
 */
class ConversationController extends Controller {

        /**
	  * @Route("/", name="maf_convs")
	  */
	public function indexAction() {
                $char = $this->get('dispatcher')->gateway('conversationListTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }
		$convs = $this->get('conversation_manager')->getConversations($char);

		return $this->render('Conversation/index.html.twig', [
			'conversations' => $convs,
			'char' => $char,
		]);
	}

	/**
	  * @Route("/summary", name="maf_conv_summary")
	  */
	public function summaryAction() {
                $char = $this->get('dispatcher')->gateway('conversationSummaryTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		$unread = $this->get('conversation_manager')->getUnreadConvPermissions($char); #ArrayCollection
		$active = $this->get('conversation_manager')->getActiveConvPermissions($char); #ArrayCollection
		$total = $this->get('conversation_manager')->getConversationsCount($char); #Integer

		$new = ['messages' => 0, 'conversations' => 0];
		foreach ($unread as $perm) {
			$new['messages'] += $perm->getUnread();
			$new['conversations']++;
		}

		return $this->render('Conversation/summary.html.twig', [
			'active' => $active->count(),
			'total' => $total,
			'new' => $new,
			'flagged' => 0,
			'unread' => $unread,
			'local_news' => $this->get('news_manager')->getLocalList($char)
		]);
	}

	/**
	  * @Route("/unread", name="maf_unread")
	  */
	public function unreadAction() {
                $char = $this->get('dispatcher')->gateway('conversationUnreadTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		return $this->render('Conversation/unread.html.twig', [
			'unread' => $this->get('conversation_manager')->getUnreadConvPermissions($char),
		]);
	}

	/**
	  * @Route("/contacts", name="maf_contacts")
	  */
	public function contactsAction() {
		# TODO: All of this function. And all the supporting code because, lol.
                $char = $this->get('dispatcher')->gateway('conversationContactsTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($character);
                }

		return array('contacts' => $this->get('conversation_manager')->getLegacyContacts($char));
	}

	/**
	  * @Route("/read/{conv}", name="maf_conv_read", requirements={"conv"="\d+"})
	  */
	public function readAction(Conversation $conv) {
                $char = $this->get('dispatcher')->gateway('conversationSingleTest', false, true, false, $conv);
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		$messages = $conv->findMessages($char);
		$perms = $conv->findCharPermissions($char);
		$lastPerm = $perms->last();
		$unread = $lastPerm->getUnread();
		$total = $messages->count();

		if ($unread) {
			$lastPerm->setUnread(NULL);
			$i = 0;
			foreach ($messages as $m) {
				$i++;
				if ($i == $total - $unread) {
					$last = $m->getSent();
					break;
				}
			}
		} else {
			$unread = 0;
			$last = NULL;
		}

		#Find the timestamp of the last read message.

		$veryold = new \DateTime('now');
		$veryold->sub(new \DateInterval("P30D")); // TODO: make this user-configurable

		return $this->render('Conversation/conversation.html.twig', [
			'conversation' => $conv,
			'messages' => $messages,
			'total' => $total,
			'unread' => $unread,
			'veryold' => $veryold,
			'last' => $last,
		]);
	}

	/**
	  * @Route("/participants/{conv}", name="maf_conv_participants", requirements={"conv"="\d+"})
	  */
	public function participantsAction(ConversationMetadata $meta) {
		# TODO: All of this function.
		$user = $this->get('message_manager')->getCurrentUser();

		if ($meta->getUser() != $user) {
			throw new AccessDeniedHttpException($this->get('translator')->trans('error.conversation.noaccess', array(), "MsgBundle"));
		}

		$metas = $meta->getConversation()->getMetadata();

		$em = $this->getDoctrine()->getManager();
		$rights = $em->getRepository('MsgBundle:Right')->findAll();

		return array('metas'=>$metas, 'rights'=>$rights, 'my_meta'=>$meta);
	}

	/**
		* @Route("/new", name="maf_conv_new")
		* @Route("/new/{realm}", name="maf_conv_realm_new")
		*/
	public function newconversationAction(Request $request, Realm $realm=null) {
		# TODO: All of this function.
		$user = $this->get('message_manager')->getCurrentUser();
		$character = $this->get('appstate')->getCharacter();
		if (! $character instanceof Character) {
			return $this->redirectToRoute($character);
		}

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
			if (isset($data['captor'])) foreach ($data['captor'] as $rec) {
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

		return $this->render('Conversation/conversation.html.twig', [
			'form' => $form->createView(),
			'realm' => $realm
		]);
	}


	/**
	  * @Route("/reply/{conv}", name="maf_conv_reply", requirements={"conv"="\d+"})
	  */
	public function replyAction(Conversation $conv, Request $request) {
                $char = $this->get('dispatcher')->gateway('conversationReplyTest', false, true, false, $conv);
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		$form = $this->createForm(new MessageReplyType());

		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			$em = $this->getDoctrine()->getManager();
			$replyTo = $data['reply_to'];

			$message = $this->get('conversation_manager')->writeMessage($conv, $replyTo, $char, $data['content'], $data['type']);

			return new RedirectResponse($this->generateUrl('maf_conv_read', ['conv' => $conv->getId()]).'#'.$message->getId());
			/*
			When we move past Symfony 3.1, use the below:
			reutrn new RedirectResponse($this->generateUrl('maf_conv_read', ['conv' => $conv->getId(), '_fragment' => $message->getId()]));
			*/
		}

		return $this->render('Conversation/reply.html.twig', [
			'form' => $form->createView()
		]);
	}
}
