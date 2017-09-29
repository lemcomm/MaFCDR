<?php

namespace Calitarus\MessagingBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use Calitarus\MessagingBundle\Entity\ConversationMetadata;


/**
 * @Route("/manage")
 */
class ManageController extends Controller {

	/**
		* @Route("/participants/{meta}", name="cmsg_participants", requirements={"meta"="\d+"})
		* @Template
		*/
	public function participantsAction(ConversationMetadata $meta) {
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
		* @Route("/participants_remove", name="cmsg_participant_remove")
		*/
	public function participantsremoveAction(Request $request) {
		$user = $this->get('message_manager')->getCurrentUser();
		$em = $this->getDoctrine()->getManager();
		$meta = $em->getRepository('MsgBundle:ConversationMetadata')->find($request->request->get('meta'));

		if (!$meta || $meta->getUser() != $user) {
			throw new AccessDeniedHttpException($this->get('translator')->trans('error.conversation.noaccess', array(), "MsgBundle"));
		}
		if (!$meta->hasRightByName('remove') && !$meta->hasRightByName('owner')) {
			throw new AccessDeniedHttpException($this->get('translator')->trans('error.conversation.noright', array(), "MsgBundle"));			
		}

		$target = $em->getRepository('MsgBundle:User')->find($request->request->get('id'));
		$target_meta = $meta->getConversation()->findMeta($target);
		if ($target_meta && !$target_meta->hasRightByName('owner')) {
			$this->get('message_manager')->leaveConversation($target_meta, $target);
			$em->flush();
			return new JsonResponse(true); 
		} else {
			return new JsonResponse(false);
		}
	}


	/**
		* @Route("/conversation/leave", name="cmsg_leave", defaults={"_format"="json"})
		*/
	public function leaveAction(Request $request) {
		$user = $this->get('message_manager')->getCurrentUser();

		$id = $request->request->get('id');

		$meta = $this->getDoctrine()->getManager()->getRepository('MsgBundle:ConversationMetadata')->find($id);
		if (!$meta || $meta->getUser() != $user) {
			throw new AccessDeniedHttpException($this->get('translator')->trans('error.conversation.noaccess', array(), "MsgBundle"));
		}

		$convos =  $this->get('message_manager')->leaveConversation($meta, $user);

		$this->getDoctrine()->getManager()->flush();
		
		return new Response(json_encode($convos));
	}

}
