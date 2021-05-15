<?php

namespace BM2\SiteBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\FlattenException;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;


/**
 * @Route("/error")
 */
class ExceptionController extends Controller {
	/**
	 * Converts an Exception to a Response.
	 *
	 * @param FlattenException     $exception A FlattenException instance
	 * @param DebugLoggerInterface $logger    A DebugLoggerInterface instance
	 * @param string               $format    The format to use for rendering (html, xml, ...)
	 * @param Boolean              $embedded  Whether the rendered Response will be embedded or not
	 *
	 * @throws \InvalidArgumentException When the exception template does not exist
	 * @Route("/")
	 */
	public function exceptionAction(FlattenException $exception, DebugLoggerInterface $logger = null, $format = 'html', $embedded = false) {
		return $this->render('Exception/exception.html.twig', [
			'status_code' => $exception->getStatusCode(), 
			'status_text' => $exception->getMessage()
		]);
	}
}
