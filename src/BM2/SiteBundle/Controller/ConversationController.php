<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Conversation;
use BM2\SiteBundle\Entity\Message;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Form\MessageReplyType;
use BM2\SiteBundle\Form\NewConversationType;

use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
		return new Response("Feature not yet implemented. Try again later.");
                $char = $this->get('dispatcher')->gateway('conversationContactsTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($character);
                }

		return new Response(['contacts' => $this->get('conversation_manager')->getLegacyContacts($char)]);
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
			$lastPerm->setUnread(0);
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
		if ($lastPerm->getActive()) {
			$lastPerm->setLastAccess(new \DateTime('now'));
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
	public function participantsAction(Conversation $conv) {
                $char = $this->get('dispatcher')->gateway('conversationManageTest', false, true, false, $conv);
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		#TODO: Make this only list the permissions we can see with our own. Because it'd be neat.

		$perms = $conv->findRelevantPermissions($char);

		return $this->render('Conversation/participants.html.twig', [
			'conv' =>$conv,
			'perms'=>$perms,
		]);
	}

	/**
	  * @Route("{conv}/demote/{perm}/{var}", name="maf_conv_manage", requirements={"conv"="\d+", "perm"="\d+", "var"="\d+"})
	  */
	public function changePermissionAction(Conversation $conv, ConversationPermission $perm, $var) {
                $char = $this->get('dispatcher')->gateway('conversationChangeTest', false, true, false, $conv);
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		if ($conv->findActivePermissions($char)) {

		}

		$active = $conv->findActivePermissions();

		return $this->render('Conversation/conversation.html.twig', [
			'perms'=>$active,
			'my_meta'=>$meta
		]);
	}

	/**
		* @Route("/new", name="maf_conv_new")
		* @Route("/new/r/{realm}", name="maf_conv_realm_new")
		*/
	public function newConversationAction(Request $request, Realm $realm=null) {
                $char = $this->get('dispatcher')->gateway('conversationNewTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		if ($realm && !$char->findRealms()->contains($realm)) {
			$realm = null;
		}

		if ($realm) {
			$contacts = null;
			$distance = null;
			$settlement = null;
		} else {
			if ($char->getAvailableEntourageOfType("herald")->isEmpty()) {
				$distance = $this->get('geography')->calculateInteractionDistance($char);
			} else {
				$distance = $this->get('geography')->calculateSpottingDistance($char);
			}
			$this->get('dispatcher')->setCharacter($char);
			$settlement = $this->get('dispatcher')->getActionableSettlement();
			$contacts = $this->get('conversation_manager')->getLegacyContacts($char);
		}

		$form = $this->createForm(new NewConversationType($contacts, $distance, $char, $settlement, $realm));

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();

			$recipients = new ArrayCollection;
			if (isset($data['nearby'])) foreach ($data['nearby'] as $rec) {
				if (!$recipients->contains($rec)) {
					$recipients->add($rec);
				}
			}
			if (isset($data['captor'])) foreach ($data['captor'] as $rec) {
				if (!$recipients->contains($rec)) {
					$recipients->add($rec);
				}
			}
			if (isset($data['contacts'])) foreach ($data['contacts'] as $rec) {
				if (!$recipients->contains($rec)) {
					$recipients->add($rec);
				}
			}
			if (isset($data['owner'])) foreach ($data['owner'] as $rec) {
				if (!$recipients->contains($rec)) {
					$recipients->add($rec);
				}
			}

			$conv = $this->get('conversation_manager')->newConversation($char, $recipients, $data['topic'], $data['type'], $data['content'], $realm);
			if ($conv === 'no recipients') {
				#TODO: Throw exception!
			}
			$url = $this->generateUrl('maf_conv_read', ['conv' => $conv->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
			$this->addFlash('notice', $this->get('translator')->trans('conversation.created', ["%url%"=>$url], 'conversations'));
			return $this->redirectToRoute('maf_conv_summary');
		}

		return $this->render('Conversation/new.html.twig', [
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
