<?php

namespace BM2\SiteBundle\Controller;

use BM2\SiteBundle\Entity\NewsArticle;
use BM2\SiteBundle\Entity\NewsEdition;
use BM2\SiteBundle\Entity\NewsEditor;
use BM2\SiteBundle\Entity\NewsPaper;
use BM2\SiteBundle\Entity\NewsReader;
use BM2\SiteBundle\Form\InteractionType;
use BM2\SiteBundle\Form\NewsArticleType;
use BM2\SiteBundle\Form\NewsEditorType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;


/**
 * @Route("/publications")
 */
class NewsController extends Controller {

	/**
		* @Route("/", name="bm2_news")
		* @Template("BM2SiteBundle:News:current.html.twig")
		*/
	public function indexAction() {
		$character = $this->get('appstate')->getCharacter();

		return array(
			"editor_list"=>$character->getNewspapersEditor(),
			"reader_list"=>$character->getNewspapersReader(),
			"local_list"=>$this->get('news_manager')->getLocalList($character),
			"can_create"=>$this->get('news_manager')->canCreatePaper($character)
		);
	}

	/**
	  * @Route("/read/{edition}", name="bm2_site_news_read", requirements={"edition"="\d+"})
	  * @Template
	  */
	public function readAction(NewsEdition $edition, Request $request) {
		$character = $this->get('appstate')->getCharacter();

		$reader = $this->get('news_manager')->readEdition($edition, $character);
		if (!$reader) {
			throw new AccessDeniedHttpException('error.noaccess.edition');
		}

		$can_subscribe = false;
		if (true === $reader) {
			// temporary access of a local publication
			// FIXME: check for the $paper->getSubscription() boolean we've already defined - but right now the paper owner can't change it anywhere
			$can_subscribe = true;
		} elseif ($reader->getRead()===false || $reader->getUpdated()===true) {
			$reader->setRead(true);
			$reader->setUpdated(false);
			$this->getDoctrine()->getManager()->flush();
		}

		return array(
			'paper'	=>	$edition->getPaper(),
			'edition' => $edition,
			'can_subscribe' => $can_subscribe
		);
	}


	 /**
		 * @Route("/subscribe/{edition}", requirements={"edition"="\d+"})
		 * @Template
		 */
	 public function subscribeAction(NewsEdition $edition) {
		$character = $this->get('appstate')->getCharacter();

		// FIXME: catch exception if $paper can not be found and throw this:
		//			throw $this->createNotFoundException('error.notfound.paper');

		$reader = $this->get('news_manager')->readEdition($edition, $character);
		if (!$reader) {
			throw new AccessDeniedHttpException('error.noaccess.edition');
		}
		if (true === $reader) {
			$reader = new NewsReader;
			$reader->setCharacter($character);
			$reader->setEdition($edition);
			$reader->setRead(true)->setUpdated(false);
			$em = $this->getDoctrine()->getManager();
			$em->persist($reader);
			$em->flush();
			$this->addFlash('notice', $this->get('translator')->trans('news.subscribe.done', array('%paper%'=>$edition->getPaper()->getName()), 'communication'));

		}
		return new RedirectResponse($this->container->get('router')->generate('bm2_news'));
	}


	/**
		* @Route("/create")
		* @Template
		*/
	public function createAction(Request $request) {
		$character = $this->get('appstate')->getCharacter();

		if (!$this->get('news_manager')->canCreatePaper($character)) {
			throw new AccessDeniedHttpException('error.noaccess.library');
		}

		$form = $this->createFormBuilder()
			->add('name', 'text', array(
				'required'=>true, 
				'label'=>'news.create.newname',
				'translation_domain' => 'communication'
				))
			->getForm();
		$form->handleRequest($request);
		if ($form->isValid()) {
			// TODO: validation ?
			$data = $form->getData();

			$newpaper = $this->get('news_manager')->newPaper($data['name'], $character);
			$this->getDoctrine()->getManager()->flush();

			return new RedirectResponse($this->container->get('router')->generate('bm2_site_news_editor', array('paper' => $newpaper->getId()
				)));
		}

		return array('form'=>$form->createView());
	}

	 /**
		 * @Route("/editor/{paper}", requirements={"paper"="\d+"})
		 * @Template
		 */
	 public function editorAction(NewsPaper $paper, Request $request) {
		$character = $this->get('appstate')->getCharacter();

		$editor = $this->get('news_manager')->accessPaper($paper, $character);
		if (!$editor) {
			throw new AccessDeniedHttpException('error.noaccess.paper');
		}

		$form = $this->createForm(new NewsEditorType($paper));

		return array(
			'paper'	=>	$paper,
			'editor'	=> $editor,
			'form'	=> $form->createView()
		);

	}

	 /**
		 * @Route("/editorchange", defaults={"_format"="json"})
		 */
	 public function editorchangeAction(Request $request) {
		$character = $this->get('appstate')->getCharacter();

		if ($request->isMethod('POST')) {
			$paperId = $request->request->get('paper');
			$targetId = $request->request->get('character');
			$paper = $this->getDoctrine()->getManager()->getRepository('BM2SiteBundle:NewsPaper')->find($paperId);
			if (!$paper) {
				throw $this->createNotFoundException('error.notfound.paper');
			}

			$editor = $this->get('news_manager')->accessPaper($paper, $character);
			if (!$editor || $editor->getOwner()===false) {
				throw new AccessDeniedHttpException('error.noaccess.paperowner');
			}

			$form = $this->createForm(new NewsEditorType($paper));
			$form->handleRequest($request);
			if ($form->isValid()) {
				$data = $form->getData();

				$target = $this->getDoctrine()->getManager()->getRepository('BM2SiteBundle:Character')->find($targetId);
				if (!$target) {
					throw $this->createNotFoundException('error.notfound.character');
				}
				$target_editor = $this->get('news_manager')->accessPaper($paper, $target);
				if (!$target_editor) {
					throw $this->createNotFoundException('error.notfound.character');
				}
				// TODO: make sure the paper always has at least one owner!
				// TODO: probably move this into the news manager
				if (!$data['owner'] && !$data['editor'] && !$data['author'] && !$data['publisher']) {
					$this->getDoctrine()->getManager()->remove($target_editor);
					$paper->removeEditor($target_editor);
					$target->removeNewspapersEditor($target_editor);
				} else {
					$target_editor->setOwner($data['owner']);
					$target_editor->setEditor($data['editor']);
					$target_editor->setAuthor($data['author']);
					$target_editor->setPublisher($data['publisher']);					
				}

				// TODO: notify target

				$this->getDoctrine()->getManager()->flush();
				return new Response(json_encode(true));
			}
		}

		return new Response(json_encode(false));
	}


	 /**
		 * @Route("/editoraddform/{paperId}", requirements={"paperId"="\d+"})
		 * @Template
		 */
	 public function editoraddformAction($paperId, Request $request) {
		$character = $this->get('appstate')->getCharacter();
		$distance = $this->get('geography')->calculateInteractionDistance($character);
		$form = $this->createForm(new InteractionType(
			'publication',
			$distance,
			$character,
			true
		));
		return array(
			'paperid'=>$paperId,
			'form'=>$form->createView()
		);
	 }


	 /**
		 * @Route("/editoradd")
		 */
	 public function editoraddAction(Request $request) {
		$character = $this->get('appstate')->getCharacter();

		if ($request->isMethod('POST')) {
			$paperId = $request->request->get('paper');
			$paper = $this->getDoctrine()->getManager()->getRepository('BM2SiteBundle:NewsPaper')->find($paperId);
			if (!$paper) {
				throw $this->createNotFoundException('error.notfound.paper');
			}

			$editor = $this->get('news_manager')->accessPaper($paper, $character);
			if (!$editor || $editor->getOwner()===false) {
				throw new AccessDeniedHttpException('error.noaccess.paperowner');
			}

			$distance = $this->get('geography')->calculateInteractionDistance($character);
			$form = $this->createForm(new InteractionType(
				'publication',
				$distance,
				$character,
				true
			));
			$form->handleRequest($request);
			if ($form->isValid()) {
				$data = $form->getData();

				foreach ($data['target'] as $target) {
					$target_editor = $this->get('news_manager')->accessPaper($paper, $target);
					// FIXME: json response doesn't work here, use something else!
					if ($target_editor) {
						$this->addFlash('notice', $this->get('translator')->trans('error.iseditor', array('%character%'=> $target->getName())));

					} else {
						$this->get('news_manager')->addEditor($paper, $target);
					}
				}

				$this->getDoctrine()->getManager()->flush();
				return new RedirectResponse($this->container->get('router')->generate('bm2_site_news_editor', array('paper' => $paperId)));
			}
		}

		return new Response(json_encode(false));
	}


	 /**
		 * @Route("/createedition/{paper}", requirements={"paper"="\d+"})
		 */
	 public function createeditionAction(NewsPaper $paper, Request $request) {
		$character = $this->get('appstate')->getCharacter();

		$editor = $this->get('news_manager')->accessPaper($paper, $character);
		if (!$editor || $editor->getEditor()===false) {
			throw new AccessDeniedHttpException('error.noaccess.paper');
		}

		$edition = $this->get('news_manager')->newEdition($paper);
		if ($paper->getEditions()->count()<=1) {
			// first edition - add example articles
			for ($i=0;$i<4;$i++) {
				$article = new NewsArticle;
				$article->setAuthor($character);
				$article->setWritten(new \DateTime("now"));
				$article->setEdition($edition);
				$article->setTitle($this->get('translator')->trans('news.examples.title'.$i, array(), "communication"));
				$article->setContent($this->get('translator')->trans('news.examples.content'.$i, array(), "communication"));
				$this->get('news_manager')->addArticle($article);
				switch ($i) {
					case 0:	$article->setRow(1)->setCol(3)->setSizeX(2)->setSizeY(2); break;
					case 1:	$article->setRow(1)->setCol(1)->setSizeX(2)->setSizeY(1); break;
					case 2:	$article->setRow(2)->setCol(1)->setSizeX(1)->setSizeY(1); break;
					case 3:	$article->setRow(2)->setCol(2)->setSizeX(1)->setSizeY(1); break;
				}
			}
		}
		$this->getDoctrine()->getManager()->flush();

		return new RedirectResponse($this->container->get('router')->generate('bm2_site_news_edition', array('edition' => $edition->getId())));
	 }

	 /**
		 * @Route("/edition/{edition}", requirements={"edition"="\d+"})
		 * @Template
		 */
	 public function editionAction(NewsEdition $edition, Request $request) {
		$character = $this->get('appstate')->getCharacter();

		$editor = $this->get('news_manager')->accessPaper($edition->getPaper(), $character);
		if (!$editor || $editor->getEditor()===false) {
			throw new AccessDeniedHttpException('error.noaccess.edition');
		}

		$article = new NewsArticle;
		$article->setEdition($edition);
		$form = $this->createForm(new NewsArticleType, $article);

		return array(
			'paper'	=>	$edition->getPaper(),
			'editor'	=> $editor,
			'edition' => $edition,
			'form' => $form->createView()
		);
	}

	 /**
	  * @Route("/publish/{edition}", defaults={"_format"="json"})
	  * @Method({"POST"})
	  */
	public function publishAction(NewsEdition $edition) {
		$character = $this->get('appstate')->getCharacter();

		$editor = $this->get('news_manager')->accessPaper($edition->getPaper(), $character);
		if (!$editor || $editor->getEditor()===false) {
			throw new AccessDeniedHttpException('error.noaccess.edition');
		}

		$this->get('news_manager')->publishEdition($edition);
		$this->getDoctrine()->getManager()->flush();

		return new Response(json_encode(array('success'=>true)));
	}

	 /**
	  * @Route("/layout", defaults={"_format"="json"})
	  * @Method({"POST"})
	  */
	 public function layoutAction(Request $request) {
		$character = $this->get('appstate')->getCharacter();

		$editionId = $request->request->get('edition');
		$layout_data = json_decode($request->request->get('layout'));
		$layout = array();
		foreach ($layout_data as $data) {
			$layout[$data->article] = $data;
		}

		$edition = $this->getDoctrine()->getManager()->getRepository('BM2SiteBundle:NewsEdition')->find($editionId);
		if (!$edition) {
			return new Response(json_encode(array(
				'success'=>false,
				'message'=>$this->get('translator')->trans('error.notfound.edition'),
			)));
		}

		$editor = $this->get('news_manager')->accessPaper($edition->getPaper(), $character);
		if (!$editor || $editor->getEditor()===false) {
			return new Response(json_encode(array(
				'success'=>false,
				'message'=>$this->get('translator')->trans('error.noaccess.edition'),
			)));
		}

		foreach ($edition->getArticles() as $article) {
			if (isset($layout[$article->getId()])) {
				$box = $layout[$article->getId()];
				$article->setRow($box->row);
				$article->setCol($box->col);
				$article->setSizeX($box->x);
				$article->setSizeY($box->y);
			}
		}
		$this->getDoctrine()->getManager()->flush();

		return new Response(json_encode(array('success'=>true)));
	}

	/**
	  * @Route("/newarticle")
	  * @Template
	  */
	public function newarticleAction(Request $request) {
		$character = $this->get('appstate')->getCharacter();

		$article = new NewsArticle;
		$form = $this->createForm(new NewsArticleType, $article);
		$form->handleRequest($request);
		if ($form->isValid()) {
			$article = $form->getData();

			$paper = $article->getEdition()->getPaper();
			if (!$paper) {
				throw $this->createNotFoundException('error.notfound.paper');
			}

			$editor = $this->get('news_manager')->accessPaper($paper, $character);
			if (!$editor || $editor->getEditor()===false) {
				throw new AccessDeniedHttpException('error.noaccess.paper');
			}

			$article->setAuthor($character);
			$article->setWritten(new \DateTime("now"));
			$this->get('news_manager')->addArticle($article);
			$this->getDoctrine()->getManager()->flush();
		}

		return new RedirectResponse($this->container->get('router')->generate('bm2_site_news_edition', array('edition' => $article->getEdition()->getId())));
	 }

	/**
	  * @Route("/editarticle/{article}")
	  * @Template
	  */
	public function editarticleAction(NewsArticle $article, Request $request) {
		$character = $this->get('appstate')->getCharacter();

		$form = $this->createForm(new NewsArticleType, $article);
		$form->handleRequest($request);
		if ($form->isValid()) {
			$article = $form->getData();

			$paper = $article->getEdition()->getPaper();
			if (!$paper) {
				throw $this->createNotFoundException('error.notfound.paper');
			}

			$editor = $this->get('news_manager')->accessPaper($paper, $character);
			if (!$editor || $editor->getEditor()===false) {
				throw new AccessDeniedHttpException('error.noaccess.paper');
			}

			$article->setAuthor($character);
			$article->setWritten(new \DateTime("now"));
			$this->getDoctrine()->getManager()->flush();
		}

		return new RedirectResponse($this->container->get('router')->generate('bm2_site_news_edition', array('edition' => $article->getEdition()->getId())));
	 }

	/**
	  * @Route("/storearticle/{article}")
	  * @Method({"POST"})
	  * @Template
	  */
	public function storearticleAction(NewsArticle $article, Request $request) {
		$character = $this->get('appstate')->getCharacter();

		$paper = $article->getEdition()->getPaper();
		if (!$paper) {
			throw $this->createNotFoundException('error.notfound.paper');
		}
		$editor = $this->get('news_manager')->accessPaper($paper, $character);
		if (!$editor || $editor->getEditor()===false) {
			throw new AccessDeniedHttpException('error.noaccess.paper');
		}

		$em = $this->getDoctrine()->getManager();
		$collection = $em->getRepository('BM2SiteBundle:NewsEdition')->findOneBy(array('paper'=>$paper, 'collection'=>true));
		if (!$collection) {
			// FIXME: should never happen
			throw $this->createNotFoundException("Article collection not found for paper {$paper->getId()} - this should never happen. Please report as a bug.");
		}

		$returnto = $article->getEdition()->getId();
		$article->setEdition($collection);
		$em->flush();

		$this->addFlash('notice', $this->get('translator')->trans('news.moved', array(), 'communication'));

		return new RedirectResponse($this->container->get('router')->generate('bm2_site_news_edition', array('edition' => $returnto)));
	}


	/**
	  * @Route("/restorearticle/{article}")
	  * @Method({"POST"})
	  * @Template
	  */
	public function restorearticleAction(NewsArticle $article, Request $request) {
		$character = $this->get('appstate')->getCharacter();

		$paper = $article->getEdition()->getPaper();
		if (!$paper) {
			throw $this->createNotFoundException('error.notfound.paper');
		}
		$editor = $this->get('news_manager')->accessPaper($paper, $character);
		if (!$editor || $editor->getEditor()===false) {
			throw new AccessDeniedHttpException('error.noaccess.paper');
		}


		$em = $this->getDoctrine()->getManager();
		$newedition = $em->getRepository('BM2SiteBundle:NewsEdition')->find($request->request->get("edition"));
		if (!$newedition) {
			throw $this->createNotFoundException('form error: invalid edition');
		}
		if ($newedition->getPaper() != $paper) {
			throw new AccessDeniedHttpException('form error: wrong paper');
		}
		if ($newedition->isPublished()) {
			throw new AccessDeniedHttpException('form error: edition already published');
		}

		$article->setEdition($newedition);
		$em->flush();

		$this->addFlash('notice', $this->get('translator')->trans('news.restored', array(), 'communication'));

		return new RedirectResponse($this->container->get('router')->generate('bm2_site_news_edition', array('edition' => $article->getEdition()->getId())));
	}


	/**
	  * @Route("/delarticle/{article}")
	  * @Method({"POST"})
	  * @Template
	  */
	public function delarticleAction(NewsArticle $article, Request $request) {
		$character = $this->get('appstate')->getCharacter();

		$paper = $article->getEdition()->getPaper();
		if (!$paper) {
			throw $this->createNotFoundException('error.notfound.paper');
		}
		$editor = $this->get('news_manager')->accessPaper($paper, $character);
		if (!$editor || $editor->getEditor()===false) {
			throw new AccessDeniedHttpException('error.noaccess.paper');
		}

		$em = $this->getDoctrine()->getManager();
		$collection = $em->getRepository('BM2SiteBundle:NewsEdition')->findOneBy(array('paper'=>$paper, 'collection'=>true));
		if (!$collection) {
			// FIXME: should never happen
			throw $this->createNotFoundException("Article collection not found for paper {$paper->getId()} - this should never happen. Please report as a bug.");
		}

		$returnto = $article->getEdition()->getId();
		$article->getEdition()->removeArticle($article);
		$em->remove($article);
		$em->flush();

		$this->addFlash('notice', $this->get('translator')->trans('news.del', array(), 'communication'));

		return new RedirectResponse($this->container->get('router')->generate('bm2_site_news_edition', array('edition' => $returnto)));
	}
}
