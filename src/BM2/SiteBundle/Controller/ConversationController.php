<?php

namespace BM2\SiteBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use BM2\SiteBundle\Entity\Association;
use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Conversation;
use BM2\SiteBundle\Entity\ConversationPermission;
use BM2\SiteBundle\Entity\House;
use BM2\SiteBundle\Entity\Message;
use BM2\SiteBundle\Entity\Realm;
use BM2\SiteBundle\Form\AddParticipantType;
use BM2\SiteBundle\Form\AreYouSureType;
use BM2\SiteBundle\Form\MessageReplyType;
use BM2\SiteBundle\Form\NewConversationType;
use BM2\SiteBundle\Form\NewLocalMessageType;
use BM2\SiteBundle\Form\RecentMessageReplyType;

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
		$convs = $this->get('conversation_manager')->getPrivateConversations($char);

		if (count($convs) >= 150) {
			$warn = true;
		} else {
			$warn = false;
		}

		return $this->render('Conversation/index.html.twig', [
			'orgs' => false,
			'conversations' => $convs,
			'char' => $char,
			'warning' => $warn
		]);
	}

        /**
	  * @Route("/orgs", name="maf_convs_orgs")
	  */
	public function orgsAction() {
                $char = $this->get('dispatcher')->gateway('conversationListTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }
		$convs = $this->get('conversation_manager')->getOrgConversations($char);

		return $this->render('Conversation/orgs.html.twig', [
			'convs' => $convs,
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
		$private = $this->get('conversation_manager')->getPrivateConversationsCount($char); #Integer
		$privateActive = $this->get('conversation_manager')->getActivePrivateConversationsCount($char); #Integer
		$org = $this->get('conversation_manager')->getOrgConversationsCount($char); #Integer
		$orgActive = $this->get('conversation_manager')->getActiveOrgConversationsCount($char); #Integer

		$new = ['messages' => 0, 'conversations' => 0];
		foreach ($unread as $perm) {
			$new['messages'] += $perm->getUnread();
			$new['conversations']++;
		}

		return $this->render('Conversation/summary.html.twig', [
			'private' => $private,
			'privateActive' =>$privateActive,
			'org' => $org,
			'orgActive' => $orgActive,
			'new' => $new,
			'flagged' => 0,
			'unread' => $unread,
			'local_news' => $this->get('news_manager')->getLocalList($char)
		]);
	}

	/**
	  * @Route("/unread", name="maf_conv_unread")
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
	  * @Route("/new", name="maf_conv_new")
	  * @Route("/new/r{realm}", name="maf_conv_realm_new")
	  * @Route("/new/h{house}", name="maf_conv_house_new")
	  * @Route("/new/a{assoc}", name="maf_conv_assoc_new")
	  */
	public function newConversationAction(Request $request, Realm $realm=null, House $house=null, Association $assoc=null) {
                $char = $this->get('dispatcher')->gateway('conversationNewTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		if ($realm && !$char->findRealms()->contains($realm)) {
			$realm = null;
		}
		if ($house && $char->getHouse() != $house) {
			$house = null;
		}
		if ($assoc && !$char->findAssociations()->contains($assoc)) {
			$assoc = null;
		}

		if ($realm || $house || $assoc) {
			$contacts = null;
			$distance = null;
			$settlement = null;
			if ($realm) {
				$org = $realm;
			} elseif ($assoc) {
				$org = $assoc;
			} else {
				$org = $house;
			}
		} else {
			$org = false;
			if ($char->getAvailableEntourageOfType("herald")->isEmpty()) {
				$distance = $this->get('geography')->calculateInteractionDistance($char);
			} else {
				$distance = $this->get('geography')->calculateSpottingDistance($char);
			}
			$this->get('dispatcher')->setCharacter($char);
			$settlement = $this->get('dispatcher')->getActionableSettlement();
			$contacts = $this->get('conversation_manager')->getLegacyContacts($char);
		}

		$form = $this->createForm(new NewConversationType($contacts, $distance, $char, $settlement, $org));

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			if (!$org) {
				$recipients = new ArrayCollection;
				if (isset($data['owner'])) foreach ($data['owner'] as $rec) {
					if (!$recipients->contains($rec)) {
						$recipients->add($rec);
					}
				}
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
				if ($recipients->contains($char)) {
					$recipients->remove($char);
				}
			} else {
				$recipients = null;
			}

			$conv = $this->get('conversation_manager')->newConversation($char, $recipients, $data['topic'], $data['type'], $data['content'], $org);
			if ($conv === 'no recipients') {
				#TODO: Throw exception!
			}
			$url = $this->generateUrl('maf_conv_read', ['conv' => $conv->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
			$this->addFlash('notice', $this->get('translator')->trans('conversation.created', ["%url%"=>$url], 'conversations'));
			return $this->redirectToRoute('maf_conv_summary');
		}

		return $this->render('Conversation/newconversation.html.twig', [
			'form' => $form->createView(),
			'realm' => $realm,
			'house' => $house,
			'assoc' => $assoc
		]);
	}

	/**
	  * @Route("/new/local", name="maf_conv_local_new")
	  */
	public function newLocalConversationAction(Request $request) {
                $char = $this->get('dispatcher')->gateway('conversationNewTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		$allNearby = $this->allNearby($char);

		$form = $this->createForm(new NewLocalMessageType($char->getInsideSettlement(), $char->getInsidePlace(), false));

		$form->handleRequest($request);
		if ($form->isSubmitted() && $form->isValid()) {
			$data = $form->getData();
			if ($data['target'] == 'local') {
				$target = new ArrayCollection();
				foreach ($allNearby as $each) {
					$target->add($each['character']);
				}
			} else {
				$target = $data['target'];
			}
			$msg = $this->get('conversation_manager')->writeLocalMessage($char, $target, $data['topic'], $data['type'], $data['content'], $data['reply_to'], $data['target']);

			$url = $this->generateUrl('maf_conv_local', [], UrlGeneratorInterface::ABSOLUTE_URL).'#'.$msg->getId();
			$this->addFlash('notice', $this->get('translator')->trans('conversation.created', ["%url%"=>$url], 'conversations'));
			return $this->redirectToRoute('maf_conv_summary');
		}

		return $this->render('Conversation/newlocal.html.twig', [
			'form' => $form->createView(),
			'nearby' => $allNearby,
		]);
	}

	private function allNearby(Character $char) {
		if ($char->getAvailableEntourageOfType("herald")->isEmpty()) {
			$distance = $this->get('geography')->calculateInteractionDistance($char);
		} else {
			$distance = $this->get('geography')->calculateSpottingDistance($char);
		}
		# findCharactersNearMe(Character $character, $maxdistance, $only_outside_settlement=false, $exclude_prisoners=true, $match_battle=false, $exclude_slumbering=false, $only_oustide_place=false)
		return $this->get('geography')->findCharactersNearMe($char, $distance, false, false);
	}

	/**
	  * @Route("/recent/reply/{msg}/{window}", name="maf_conv_recent_reply", requirements={"msg"="\d+","window"="\d+"})
	  */
	public function replyRecentAction(Request $request, Message $msg=null, string $window='0') {
                $char = $this->get('dispatcher')->gateway('conversationRecentTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		$form = $this->createForm(new RecentMessageReplyType($char->getInsideSettlement(), $char->getInsidePlace()));

		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			$allNearby = $this->allNearby($char);
			if ($data['target'] == 'local') {
				$target = new ArrayCollection();
				foreach ($allNearby as $each) {
					$target->add($each['character']);
				}
			} else {
				$target = $data['target'];
			}

			$em = $this->getDoctrine()->getManager();
			if ($msg = $em->getRepository(Message::class)->findOneById($data['reply_to'])) {
				$conv = $msg->getConversation();
		                $char = $this->get('dispatcher')->gateway('conversationReplyTest', false, true, false, $conv); # Reuse is deliberate!
		                if (! $char instanceof Character) {
		                        return $this->redirectToRoute($char);
		                }

				if ($conv->findType() != 'local') {
					#writeMessage(Conversation $conv, $replyTo = null, Character $char = null, $text, $type)
					$message = $this->get('conversation_manager')->writeMessage($conv, $msg, $char, $data['content'], $data['type']);
				} else {
					$message = $this->get('conversation_manager')->writeLocalMessage($char, $target, $data['topic'], $data['type'], $data['content'], $data['reply_to'], $data['target']);
				}

				return new RedirectResponse($this->generateUrl('maf_conv_recent', ['window' => $window]).'#'.$message->getId());
			}
		}

		return $this->render('Conversation/reply.html.twig', [
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/recent")
	  * @Route("/recent/")
	  * @Route("/recent/{window}", name="maf_conv_recent")
	  */
	public function recentAction(string $window='0') {
                $char = $this->get('dispatcher')->gateway('conversationRecentTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }
		$now = new \DateTime('now');

		$em = $this->getDoctrine()->getManager();
		$conv = $char->getLocalConversation();
		if (!$conv) {
			$conv = $this->get('conversation_manager')->newLocalConversation($char, $now);
			$em->flush();
		}

		$search = null;
		switch ($window) {
			case '0':
				$search = 'unread';
				break;
			case '1':
				$search = '-1 month';
				break;
			case '2':
				$search = '-14 days';
				break;
			case '3':
				$search = '-7 days';
				break;
			case '4':
				$search = '-3 days';
				break;
			case '5':
				$search = '-1 day';
				break;
			case '6':
				$search = '-12 hours';
				break;
			case '7':
				$search = '-2 months';
				break;
			default:
			case '8':
				$search = '-3 months';
				break;
		}
		if ($search == 'unread') {
			$all = $this->get('conversation_manager')->getAllUnreadMessages($char);
		} else {
			$all = $this->get('conversation_manager')->getAllRecentMessages($char, $search);
		}
		return $this->render('Conversation/recent.html.twig', [
			'messages' => $all,
			'period' => $window
		]);
	}

	/**
	  * @Route("/{conv}", name="maf_conv_read", requirements={"conv"="\d+"})
	  */
	public function readAction(Conversation $conv) {
                $char = $this->get('dispatcher')->gateway('conversationSingleTest', false, true, false, $conv);
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		$em = $this->getDoctrine()->getManager();
		$messages = $conv->findMessages($char);
		$perms = $conv->findCharPermissions($char);
		$lastPerm = $perms->last();
		$unread = $lastPerm->getUnread();
		$total = $messages->count();

		foreach ($perms as $each) {
			if ($each->getUnread()) {
				$each->setUnread(0);
			}
		}

		if ($unread) {
			$last = $lastPerm->getLastAccess();
		} else {
			$unread = 0;
			$last = NULL;
		}
		if ($lastPerm->getActive()) {
			$lastPerm->setLastAccess(new \DateTime('now'));
		}
		$em->flush();

		#Find the timestamp of the last read message.

		$veryold = new \DateTime('now');
		$veryold->sub(new \DateInterval("P30D")); // TODO: make this user-configurable

		if ($conv->findType() == 'org') {
			if ($assoc = $conv->getAssociation()) {
				if ($law = $assoc->findActiveLaw('rankVisibility', false)) {
					if ($law->getValue() == 'all') {
						$known = null;
						$privacy = false;
					} else {
						$privacy = true;
						$rank = $assoc->findMember($char)->getRank();
						if ($rank) {
							$known = $rank->findALlKnownCharacters();
						} else {
							$known = new ArrayCollection;
						}
					}
				}
			} else {
				$known = null;
				$privacy = false;
			}
			return $this->render('Conversation/layout_wrapper.html.twig', [
				'type' => 'org',
				'conversation' => $conv,
				'messages' => $messages,
				'veryold' => $veryold,
				'last' => $last,
				'active'=> $lastPerm->getActive(),
				'privacy' => $privacy,
				'known' => $known,
				'archive'=> false
			]);
		} else {
			return $this->render('Conversation/layout_wrapper.html.twig', [
				'type' => 'private',
				'conversation' => $conv,
				'messages' => $messages,
				'veryold' => $veryold,
				'last' => $last,
				'manager' => $lastPerm->getManager() ? true : $lastPerm->getOwner(),
				'active'=> $lastPerm->getActive(),
				'archive'=> false
			]);
		}
	}

	/**
	  * @Route("/local", name="maf_conv_local")
	  */
	public function readLocalAction() {
                $char = $this->get('dispatcher')->gateway('conversationLocalTest');
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }
		#Find the timestamp of the last read message. We do this early so we can reuse the current time.
		$now = new \DateTime('now');

		$em = $this->getDoctrine()->getManager();
		$conv = $char->getLocalConversation();
		if (!$conv) {
			$conv = $this->get('conversation_manager')->newLocalConversation($char, $now);
			$em->flush();
		}
		$messages = $conv->getMessages();
		$unread = $conv->findLocalUnread();
		$total = $messages->count();

		if ($unread) {
			foreach($unread as $msg) {
				$msg->setRead(TRUE);
			}
			$em->flush();
		}

		$veryold = $now->sub(new \DateInterval("P30D")); // TODO: make this user-configurable
		return $this->render('Conversation/layout_wrapper.html.twig', [
			'type' => 'local',
			'conversation' => $conv,
			'messages' => $messages,
			'total' => $total,
			'unread' => $unread,
			'veryold' => $veryold,
			'local'=> true,
			'active'=> false,
			'manager'=> false,
			'archive'=> false
		]);
	}

	/**
	  * @Route("/print/{conv}", name="maf_conv_print", requirements={"conv"="\d+"})
	  */
	public function printAction(Conversation $conv) {
		if ($conv->getLocalFor() != NULL) {
	                $char = $this->get('dispatcher')->gateway('conversationLocalTest', false, true, false, $conv);
	                if (! $char instanceof Character) {
	                        return $this->redirectToRoute($char);
	                }
			$messages = $conv->getMessages();
			$local = true;
		} else {
	                $char = $this->get('dispatcher')->gateway('conversationSingleTest', false, true, false, $conv);
	                if (! $char instanceof Character) {
	                        return $this->redirectToRoute($char);
	                }
			$messages = $conv->findMessages($char);
			$local = false;
			$perms = $conv->findCharPermissions($char);
			$lastPerm = $perms->last();
		}

		$org = null;
		if ($conv->getRealm()) {
			$org = $conv->getRealm();
		} elseif ($conv->getAssociation()) {
			$org = $conv->getAssociation();
		} elseif ($conv->getHouse()) {
			$org = $conv->getHouse();
		}

		#Find the timestamp of the last read message.
		$veryold = new \DateTime('now');
		$veryold->sub(new \DateInterval("P30D")); // TODO: make this user-configurable

		if ($local) {
			return $this->render('Conversation/archive.html.twig', [
				'type' => 'local',
				'conversation' => $conv,
				'messages' => $messages,
				'archive'=> true
			]);
		} elseif ($org) {
			if ($assoc = $conv->getAssociation()) {
				if ($law = $assoc->findActiveLaw('rankVisibility', false)) {
					if ($law->getValue() == 'all') {
						$known = null;
						$privacy = false;
					} else {
						$privacy = true;
						$rank = $assoc->findMember($char)->getRank();
						if ($rank) {
							$known = $rank->findALlKnownCharacters();
						} else {
							$known = new ArrayCollection;
						}
					}
				}
			} else {
				$known = null;
				$privacy = false;
			}
			return $this->render('Conversation/archive.html.twig', [
				'type' => 'org',
				'conversation' => $conv,
				'messages' => $messages,
				'veryold' => $veryold,
				'last' => NULL,
				'active'=> $lastPerm->getActive(),
				'privacy' => $privacy,
				'known' => $known,
				'archive'=> true
			]);
		} else {
			return $this->render('Conversation/archive.html.twig', [
				'type' => 'private',
				'conversation' => $conv,
				'messages' => $messages,
				'veryold' => $veryold,
				'active'=> $lastPerm->getActive(),
				'last' => NULL,
				'archive'=> true
			]);
		}
	}

	/**
	  * @Route("/{conv}/participants", name="maf_conv_participants", requirements={"conv"="\d+"})
	  */
	public function participantsAction(Conversation $conv) {
                $char = $this->get('dispatcher')->gateway('conversationManageTest', false, true, false, $conv);
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		$perms = $conv->findRelevantPermissions($char); #Get what permissions we're aware of.

		$manager = false;
		$owner = false;
		$form = null;
		if (!$conv->getRealm()) {
			if ($me = $conv->findActiveCharpermission($char)) {
				$manager = $me->getManager();
				$owner = $me->getOwner();
			}
		} else {
			$me = false;
		}

		return $this->render('Conversation/participants.html.twig', [
			'conv' =>$conv,
			'perms'=>$perms,
			'manager'=>$manager,
			'owner'=>$owner,
			'active'=>$me,
			'me'=>$char,
		]);
	}

	/**
	  * @Route("/{conv}/add", name="maf_conv_add", requirements={"conv"="\d+"})
	  */
	public function addParticipantsAction(Request $request, Conversation $conv) {
                $char = $this->get('dispatcher')->gateway('conversationAddTest', false, true, false, $conv);
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		# Dispatcher means we already know this user is either a manager or an owner, thus, they have add rights.
		$perms = $conv->findRelevantPermissions($char);
		$contacts = $this->get('conversation_manager')->getLegacyContacts($char);
		foreach ($perms as $perm) {
			if ($perm->getCharacter() && in_array($perm->getCharacter(), $contacts)) {
				unset($contacts[$perm->getCharacter()->getId()]); #Remove people who already have permissions.
			}
		}
		$form = $this->createForm(new AddParticipantType($contacts));

		$form->handleRequest($request);
		if ($form->isValid()) {
			$data = $form->getData();
			$em = $this->getDoctrine()->getManager();
			foreach($data['contacts'] as $new) {
				# Double check we can actually add this person.
				if (in_array($new, $contacts)) {
					$this->get('conversation_manager')->addParticipant($conv, $new);
					$em->flush();
				}
			}

			# These lines are just here to make this can handle single object collections.
			$all = new ArrayCollection();
			foreach ($data['contacts'] as $each) {
				$all->add($each);
			}
			$message = $this->get('conversation_manager')->newSystemMessage($conv, 'newperms', $all, $char, false);
			$em->flush();
			return new RedirectResponse($this->generateUrl('maf_conv_read', ['conv' => $conv->getId()]).'#'.$message->getId());
		}
		return $this->render('Conversation/add.html.twig', [
			'conv'=>$conv,
			'perms'=>$perms,
			'form'=>$form->createView(),
		]);
	}

	/**
	  * @Route("/{conv}/change/{perm}/{var}", name="maf_conv_change", requirements={"conv"="\d+", "perm"="\d+", "var"="\d+"})
	  */
	public function changePermissionAction(Conversation $conv, ConversationPermission $perm, $var) {
                $char = $this->get('dispatcher')->gateway('conversationChangeTest', false, true, false, $conv);
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		if ($me = $conv->findActiveCharPermission($char)) {
			$em = $this->getDoctrine()->getManager();
			$flush = false;
			$change = 'permission.invalidrequest';
			$now = new \DateTime("now");
			if ($me->getOwner()) {
				if (!$perm->getManager()) {
					if ($var == 0) {
						$perm->setActive(false);
						$perm->setEndTime($now);
						$change = 'permission.demoted.removed';
						$flush = true;
						# Yes, newSystemMessage expects a collection. So we cast the char into an array that is then cast into a collection. It works.
						$message = $this->get('conversation_manager')->newSystemMessage($conv, 'removal', new ArrayCollection([$perm->getCharacter()]), $char, false);

					} elseif ($var == 1) {
						$perm->setManager(true);
						$flush = true;
						$change = 'permission.promoted.manager';
					}
				} elseif ($perm->getOwner()) {
					if ($var == 0) {
						$perm->setOwner(false);
						$flush = true;
						$change = 'permission.demoted.manager';
					} elseif ($var === 1) {
						$change = 'permission.promoted.invalid';
					}
				} else {
					if ($var == 0) {
						$perm->setManager(false);
						$flush = true;
						$change = 'permission.demoted.user';
					} elseif ($var == 1) {
						$perm->setOwner(true);
						$flush = true;
						$change = 'permission.promoted.owner';
					}
				}
			} elseif ($me->getManager()) {
				if ($perm->getOwner() || $perm->getManager()) {
					$change = 'permission.invalidrequest';
				} else {
					if ($var === 0) {
						$perm->setActive(false);
						$perm->setEndTime($now);
						$flush = true;
						$change = 'permission.demoted.removed';
						$message = $this->get('conversation_manager')->newSystemMessage($conv, 'removal', new ArrayCollection([$perm->getCharacter()]), $char, false);
					} elseif ($var === 1) {
						$change = 'permission.nopromoteright';
					}
				}
			}
			if ($flush) {
				$em->flush();
			}
		}

		$this->addFlash('notice', $this->get('translator')->trans($change, ["%name%"=>$perm->getCharacter()->getName()], 'conversations'));

		return $this->redirectToRoute('maf_conv_participants', ['conv'=>$conv->getId()]);
	}

	/**
	  * @Route("/{conv}/leave", name="maf_conv_leave", requirements={"conv"="\d+"})
	  */
	public function leaveAction(Request $request, Conversation $conv) {
                $char = $this->get('dispatcher')->gateway('conversationLeaveTest', false, true, false, $conv);
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		$form = $this->createForm(new AreYouSureType());
		$form->handleRequest($request);

		if ($form->isValid() && $form->isSubmitted()) {
			if ($form->getData()['sure']) {
				$perm = $conv->findActiveCharPermission($char);
				if ($perm) {
					$em = $this->getDoctrine()->getManager();
					$perm->setActive(false);
					$perm->setEndTime(new \DateTime("now"));
					$message = $this->get('conversation_manager')->newSystemMessage($conv, 'left', null, $char, false);
					if ($perm->getOwner()) {
						$perm->setOwner(false);
						$perm->setManager(false);
						$this->get('conversation_manager')->findNewOwner($conv, $char, false);
					} elseif ($perm->getManager()) {
						$perm->setManager(false);
					}
					$em->flush();
					$this->addFlash('notice', $this->get('translator')->trans('conversation.left', ["%name%"=>$perm->getConversation()->getTopic()], 'conversations'));

					return new RedirectResponse($this->generateUrl('maf_conv_read', ['conv' => $conv->getId()]).'#'.$message->getId());
				}
			}
		}

		return $this->render('Conversation/exit.html.twig', [
			'form' => $form->createView(),
			'conv' => $conv,
			'type' => 'leave'
		]);

	}

	/**
	  * @Route("/{conv}/remove", name="maf_conv_remove", requirements={"conv"="\d+"})
  	  * @Route("/{conv}/remove/", name="maf_conv_remove", requirements={"conv"="\d+"})
  	  * @Route("/{conv}/remove/{var}", name="maf_conv_remove", requirements={"conv"="\d+", "var"="\d+"})
	  */
	public function removeAction(Request $request, Conversation $conv, $var = null) {
                $char = $this->get('dispatcher')->gateway('conversationRemoveTest', false, true, false, $conv);
                if (! $char instanceof Character) {
                        return $this->redirectToRoute($char);
                }

		$form = $this->createForm(new AreYouSureType());
		$form->handleRequest($request);

		if ($form->isValid() && $form->isSubmitted()) {
			if ($form->getData()['sure']) {
				$perms = $conv->findCharPermissions($char);
				if ($perms) {
					$em = $this->getDoctrine()->getManager();
					$wasOwner = false;
					$topic = $conv->getTopic();
					foreach ($perms as $perm) {
						if ($perm->getOwner()) {
							$wasOwner = true;
						}
						if ($perm->getActive()) {
							$message = $this->get('conversation_manager')->newSystemMessage($conv, 'left', null, $char, false);
						}
						$em->remove($perm);
					}
					$em->flush();
					$prune = $this->get('conversation_manager')->pruneConversation($conv);
					if ($prune == 'pruned') {
						if ($wasOwner) {
							$this->get('conversation_manager')->findNewOwner($conv, $char, true);
						}
					}
					$this->addFlash('notice', $this->get('translator')->trans('conversation.removed', ["%name%"=>$topic], 'conversations'));
				} else {
					$this->addFlash('notice', $this->get('translator')->trans('conversation.badremoved', ["%id%"=>$conv->getId()], 'conversations'));
				}
				if ($var == 1) {
					return new RedirectResponse($this->generateUrl('maf_convs'));
				} else {
					return new RedirectResponse($this->generateUrl('maf_conv_summary'));
				}
			}
		}

		return $this->render('Conversation/exit.html.twig', [
			'form' => $form->createView(),
			'conv' => $conv,
			'type' => 'leave'
		]);
	}

	/**
	  * @Route("/{conv}/reply", name="maf_conv_reply", requirements={"conv"="\d+"})
  	  * @Route("/{conv}/reply/{msg}", name="maf_conv_reply_msg", requirements={"conv"="\d+","msg"="\d+"})
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
			if ($message instanceof Message) {
				return new RedirectResponse($this->generateUrl('maf_conv_read', ['conv' => $conv->getId()]).'#'.$message->getId());
			} else {
				$this->addFlash('notice', $this->get('translator')->trans('error.conversation.'.$message, [], 'conversations'));
				return new RedirectResponse($this->generateUrl('maf_conv_read', ['conv' => $conv->getId()]));
			}
		}

		return $this->render('Conversation/reply.html.twig', [
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/local/reply", name="maf_conv_local_reply")
	  */
	public function replyLocalAction(Request $request) {
                $char = $this->get('dispatcher')->gateway('conversationLocalReplyTest');

		$org = false;
		if ($char->getAvailableEntourageOfType("herald")->isEmpty()) {
			$distance = $this->get('geography')->calculateInteractionDistance($char);
		} else {
			$distance = $this->get('geography')->calculateSpottingDistance($char);
		}
		# findCharactersNearMe(Character $character, $maxdistance, $only_outside_settlement=false, $exclude_prisoners=true, $match_battle=false, $exclude_slumbering=false, $only_oustide_place=false)
		$allNearby = $this->get('geography')->findCharactersNearMe($char, $distance, false, false);

		$form = $this->createForm(new NewLocalMessageType($char->getInsideSettlement(), $char->getInsidePlace(), true));

		$form->handleRequest($request);
		if ($form->isValid() && $form->isSubmitted()) {
			$data = $form->getData();
			if ($data['target'] == 'local') {
				$target = new ArrayCollection();
				foreach ($allNearby as $each) {
					$target->add($each['character']);
				}
			} else {
				$target = $data['target'];
			}

			$msg = $this->get('conversation_manager')->writeLocalMessage($char, $target, $data['topic'], $data['type'], $data['content'], $data['reply_to'], $data['target']);

			return new RedirectResponse($this->generateUrl('maf_conv_local').'#'.$msg->getId());
		}

		return $this->render('Conversation/reply.html.twig', [
			'form' => $form->createView()
		]);
	}

	/**
	  * @Route("/local/remove/{msg}/{source}", name="maf_conv_local_remove", requirements={"msg"="\d+", "source"="\d+"})
	  */
	public function removeLocalAction(Request $request, Message $msg, $source=1) {
                $char = $this->get('dispatcher')->gateway('conversationLocalRemoveTest', false, true, false, $msg);
		$em = $this->getDoctrine()->getManager();

		if ($source==1) {
			$query = $em->createQuery('SELECT m FROM BM2SiteBundle:Message m WHERE m.conversation = :conv AND m.sent <= :date ORDER BY m.sent DESC');
	                $query->setParameters(['conv'=>$msg->getConversation(), 'date'=>$msg->getSent()]);
			$query->setMaxResults(1);
	                $nextOldest = $query->getResult();
		} else {
			$nextOldest = false;
		}

		$em->remove($msg);
		$em->flush();
		if ($source==1) {
			$url = 'maf_conv_local';
		} else {
			$url = 'maf_conv_recent';
		}
		if ($nextOldest) {
			return new RedirectResponse($this->generateUrl($url).'#'.$nextOldest[0]->getId());
		} else {
			return new RedirectResponse($this->generateUrl($url));
		}
	}
}
