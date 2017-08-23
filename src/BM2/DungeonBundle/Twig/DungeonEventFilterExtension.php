<?php

namespace BM2\DungeonBundle\Twig;

use Symfony\Component\Translation\Translator;
use Doctrine\ORM\EntityManager;

use BM2\SiteBundle\Twig\LinksExtension;


use BM2\DungeonBundle\Entity\DungeonEvent;


class DungeonEventFilterExtension extends \Twig_Extension {

	private $em;
	private $translator;
	private $links;

	// FIXME: type hinting for $translator removed because the addition of LoggingTranslator is breaking it
	public function __construct(EntityManager $em, $translator, LinksExtension $links) {
		$this->em = $em;
		$this->translator = $translator;
		$this->links = $links;
	}

	public function getName() {
		return 'dungeon_event_filter';
	}

	public function getFilters() {
		return array(
			'dungeoneventfilter' => new \Twig_SimpleFilter('dungeonEventFilter', array($this, 'dungeonEventFilter'), array('is_safe' => array('html'))),
		);
	}

	public function dungeonEventFilter(DungeonEvent $event) {
		$data = $this->parseData($event->getData());
		return $this->translator->trans($event->getContent(), $data, "dungeons");
	}

	private function parseData($input) {
		if (!$input) return array();
		$data=array();
		foreach ($input as $key=>$value) {
			switch ($key) {
				case 'd':
				case 'target':
					$dungeoneer = $this->em->getRepository('DungeonBundle:Dungeoneer')->find($value);
					if ($dungeoneer) {
						$data['%'.$key.'%'] = $this->links->ObjectLink($dungeoneer->getCharacter());
					} else {
						$data['%'.$key.'%'] = "(#$value)"; // FIXME: catch and report error, this should never happen!
					}
					break;
				case 'monster':
					$data['%'.$key.'%'] = $this->translator->transchoice("$key.$value", 1, array(), "dungeons");
					break;
				case 'size':
					$data['%'.$key.'%'] = $this->translator->trans("$key.$value", array(), "dungeons");
					break;
				case 'card':
					$card = $this->em->getRepository('DungeonBundle:DungeonCardType')->find($value);
					if ($card) {
						$data['%'.$key.'%'] = '<em>'.$this->translator->trans('card.'.$card->getName().'.title', array(), "dungeons").'</em>';
					} else {
						$data['%'.$key.'%'] = "[#$value]"; // FIXME: catch and report error, this should never happen!
					}
					break;
				default:
					$data['%'.$key.'%']=$value;
			}
		}
		return $data;
	}

}
