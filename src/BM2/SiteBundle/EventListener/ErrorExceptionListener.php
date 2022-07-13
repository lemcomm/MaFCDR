<?php

namespace BM2\SiteBundle\EventListener;

use BM2\SiteBundle\Service\DiscordIntegrator;

use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Templating\EngineInterface;

use Symfony\Component\Console\Event\ConsoleExceptionEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Psr\Log\LoggerInterface;


class ErrorExceptionListener {

	protected $templating;
	protected $logger;
	protected $discord;

	public function __construct(EngineInterface $templating, LoggerInterface $logger, DiscordIntegrator $discord) {
		$this->templating = $templating;
		$this->logger = $logger;
		$this->discord = $discord;
	}

	public function onKernelException(GetResponseForExceptionEvent $event) {
		$exception = $event->getException();
		if ($exception instanceof AccessDeniedException || $exception instanceof AccessDeniedHttpException || $exception instanceof NotFoundHttpException) {
			$data = explode('::', $exception->getMessage());
			if (isset($data[1])) {
				$domain = array_shift($data);
				$text = "";
			} else {
				$domain = 'messages';
				$text = $exception->getMessage();
				$data = array();
			}
			$params = array('domain'=>$domain, 'status_code'=>$exception->getStatusCode(), 'status_text'=>$text, 'status_data'=>$data);
			$event->setResponse(new Response($this->templating->render('Exception\error.html.twig', $params)));
		}
	}

	public function onConsoleException(ConsoleExceptionEvent $event) {
		$command = $event->getCommand();
		$exception = $event->getException();

		$message = sprintf(
			'%s: %s (uncaught exception) at %s line %s while running console command `%s`',
			get_class($exception),
			$exception->getMessage(),
			$exception->getFile(),
			$exception->getLine(),
			$command->getName()
		);
		try {
			$this->discord->pushToErrors('Error encountered!'.$message);
		} catch (Exception $e) {
			# Nothing.
		}

		$this->logger->error($message);
	}

	public function onConsoleTerminate(ConsoleTerminateEvent $event) {
		$statusCode = $event->getExitCode();
		$command = $event->getCommand();

		if ($statusCode === 0) {
			return;
		}

		if ($statusCode > 255) {
			$statusCode = 255;
			$event->setExitCode($statusCode);
		}

		$this->logger->warning(sprintf(
			'Command `%s` exited with status code %d',
			$command->getName(),
			$statusCode
		));
	}

}
