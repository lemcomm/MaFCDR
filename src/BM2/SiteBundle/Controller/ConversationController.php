<?php

namespace Calitarus\MessagingBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @Route("/read")
 */
class ReadController extends Controller {

        /**
	  * @Route("/", name="maf_convs")
	  */
	public function indexAction() {
                $char = $this->get('dispatcher')->gateway('conversationListTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($character);
                }
		$metas = $this->get('conversation_manager')->getConversations($char);

		return array('conversations' => $metas);
	}

	/**
	  * @Route("/summary", name="maf_conv_summary")
	  */
	public function summaryAction() {
                $char = $this->get('dispatcher')->gateway('conversationSummaryTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($character);
                }
                $total = 0;
                $allConvs = [];

                $new = array('messages' => 0, 'conversations' => 0);
		foreach ($char->getConvPermissions() as $perm) {
                        $id = $perm->getConversation()->getId();
                        if (!in_array($id, $allConvs)) {
                                $total++;
                                $allConvs[] = $id;
                        }
			if ($perm->getUnread() > 0) {
				$new['messages'] += $perm->getUnread();
				$new['conversations']++;
			}
		}

		return array(
			'total' => $total,
			'new' => $new,
			'unread' => $this->get('conversation_manager')->getUnreadConversations($char),
			'local_news' => $this->get('news_manager')->getLocalList($char)
		);
	}

	/**
	  * @Route("/unread", name="maf_unread")
	  */
	public function unreadAction() {
                $char = $this->get('dispatcher')->gateway('conversationAllUnreadTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($character);
                }

		return array('unread' => $this->get('conversation_manager')->getAllUnreadMessages($char));
	}

	/**
	  * @Route("/contacts", name="maf_contacts")
	  */
	public function contactsAction() {
                $char = $this->get('dispatcher')->gateway('conversationContactsTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($character);
                }

		return array('contacts' => $this->get('conversation_manager')->getLegacyContacts($char));
	}

	/**
	  * @Route("/conversation/{conv}", name="maf_conversation", requirements={"meta"="\d+"})
	  */
	public function conversationAction(Conversation $conv) {
                $char = $this->get('dispatcher')->gateway('conversationSingleTest');
                if (! $character instanceof Character) {
                        return $this->redirectToRoute($character);
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
}
