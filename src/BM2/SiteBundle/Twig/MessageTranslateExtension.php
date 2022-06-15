<?php

namespace BM2\SiteBundle\Twig;

use Symfony\Component\Translation\Translator;
use Doctrine\ORM\EntityManager;

use BM2\SiteBundle\Entity\Event;
use BM2\SiteBundle\Entity\SoldierLog;


class MessageTranslateExtension extends \Twig_Extension {

	private $em;
	private $translator;
	private $links;
	private $geo;

	private $absolute=false;

	// FIXME: type hinting for $translator removed because the addition of LoggingTranslator is breaking it
	public function __construct(EntityManager $em, $translator, LinksExtension $links, GeographyExtension $geo) {
		$this->em = $em;
		$this->translator = $translator;
		$this->links = $links;
		$this->geo = $geo;
	}


	public function getFilters() {
		return array(
			new \Twig_SimpleFilter('messagetranslate', array($this, 'messageTranslate'), array('is_safe' => array('html'))),
			new \Twig_SimpleFilter('eventtranslate', array($this, 'eventTranslate'), array('is_safe' => array('html'))),
			new \Twig_SimpleFilter('logtranslate', array($this, 'logTranslate'), array('is_safe' => array('html'))),
		);
	}

	// TODO: different strings if owner views his own log (you have... instead of has, etc.)
	public function eventTranslate(Event $event, $absolute=false) {
		$this->absolute = $absolute;
		$data = $this->parseData($event->getData());
		if ($event->getContent()=='multi') {
			$strings = array();
			foreach ($data['events'] as $subevent) {
				$subdata = $data;
				unset($subdata['events']);
				$strings[] = $this->translator->trans($subevent, $subdata, "communication");
			}
			return implode("<br />", $strings);
		} else {
			if (array_key_exists('%subtrans%', $data)) {
				if (array_key_exists('%transprefix%', $data)) {
					if (array_key_exists('%transsuffix%', $data)) {
						$data['%title%'] = $this->translator->trans($data['%transprefix%'].$data['%title%'].$data['%transsuffix%'], [], $data['%subtrans%']);
					} else {
						$data['%title%'] = $this->translator->trans($data['%transprefix%'].$data['%title%'], [], $data['%subtrans%']);
					}
				} else {
					if (array_key_exists('%transsuffix%', $data)) {
						$data['%title%'] = $this->translator->trans($data['%title%'].$data['%transsuffix%'], [], $data['%subtrans%']);
					} else {
						$data['%title%'] = $this->translator->trans($data['%title%'], [], $data['%subtrans%']);
					}
				}

			}
			return $this->translator->trans($event->getContent(), $data, "communication");
		}
	}

	public function logTranslate(SoldierLog $event) {
		$data = $this->parseData($event->getData());
		if ($event->getContent()=='multi') {
			$strings = array();
			foreach ($data['events'] as $subevent) {
				$subdata = $data;
				unset($subdata['events']);
				$strings[] = $this->translator->trans($subevent, $subdata, "communication");
			}
			return implode("<br />", $strings);
		} else {
			return $this->translator->trans($event->getContent(), $data, "communication");
		}
	}

	public function messageTranslate($input) {
		if (is_array($input) || is_object($input)) {
			$strings = array();
			foreach ($input as $in) {
				$strings[] = $this->messageTranslateOne($in);
			}
			return implode("<br />", $strings);
		} else {
			return $this->messageTranslateOne($input);
		}
	}

	public function messageTranslateOne($input) {
		$json = json_decode($input);
		if (!$json) return $input;

		if (!isset($json->text)) return $input;
		if (isset($json->data)&& (is_array($json->data)||is_object($json->data))) {
			$data=$this->parseData($json->data);
		} else {
			$data=array();
		}
		return $this->translator->trans($json->text, $data, "communication");
	}

	public function getName() {
		return 'message_translate_extension';
	}


	private function parseData($input) {
		if (!$input) return array();
		$data=array();
		if (isset($input['domain'])) {
			$domain = $input['domain'];
		} else {
			$domain = 'communication';
		}
		foreach ($input as $key=>$value) {
			if (preg_match('/%link-([^-]+)(-.*)?%/', $key, $matches)) {
				// link elements, syntax %link-(type)%
				$subkey = $matches[1];
				if (isset($matches[2])) $index = $matches[2]; else $index='';
				switch ($subkey) {
					case 'war':
						$war = $this->em->getRepository('BM2SiteBundle:War')->find($value);
						$data['%war'.$index.'%'] = $this->links->ObjectLink($war, false, $this->absolute);
						break;
					case 'artifact':
						$artifact = $this->em->getRepository('BM2SiteBundle:Artifact')->find($value);
						$data['%artifact'.$index.'%'] = $this->links->ObjectLink($artifact, false, $this->absolute);
						break;
					case 'mercenaries':
						$mercenaries = $this->em->getRepository('BM2SiteBundle:Mercenaries')->find($value);
						$data['%mercenaries'.$index.'%'] = $this->links->ObjectLink($mercenaries, false, $this->absolute);
						break;
					case 'place':
						$place = $this->em->getRepository('BM2SiteBundle:Place')->find($value);
						$data['%place'.$index.'%'] = $this->links->ObjectLink($place, false, $this->absolute);
					case 'battle':
						$battle = $this->em->getRepository('BM2SiteBundle:BattleReport')->find($value);
						$data['%battle'.$index.'%'] = $this->links->ObjectLink($battle, false, $this->absolute);
						break;
					case 'log':
						$log = $this->em->getRepository('BM2SiteBundle:EventLog')->find($value);
						$data['%log'.$index.'%'] = $this->links->ObjectLink($log, false, $this->absolute);
						break;
					case 'realm':
						$realm = $this->em->getRepository('BM2SiteBundle:Realm')->find($value);
						$data['%realm'.$index.'%'] = $this->links->ObjectLink($realm, false, $this->absolute);
						break;
					case 'realmposition':
						$position = $this->em->getRepository('BM2SiteBundle:RealmPosition')->find($value);
						$data['%realmposition'.$index.'%'] = $this->links->ObjectLink($position, false, $this->absolute);
						break;
					case 'settlement':
						$settlement = $this->em->getRepository('BM2SiteBundle:Settlement')->find($value);
						$data['%settlement'.$index.'%'] = $this->links->ObjectLink($settlement, false, $this->absolute);
						break;
					case 'character':
						$character = $this->em->getRepository('BM2SiteBundle:Character')->find($value);
						$data['%character'.$index.'%'] = $this->links->ObjectLink($character, false, $this->absolute);
						break;
					case 'buildingtype':
						$type = $this->em->getRepository('BM2SiteBundle:BuildingType')->find($value);
						$data['%buildingtype'.$index.'%'] = $this->links->ObjectLink($type, false, $this->absolute);
						break;
					case 'featuretype':
						$type = $this->em->getRepository('BM2SiteBundle:FeatureType')->find($value);
						$data['%featuretype'.$index.'%'] = $this->links->ObjectLink($type, false, $this->absolute);
						break;
					case 'item':
						// this can be 0
						if ($value==0) {
							$data['%item'.$index.'%'] = '-';
						} else {
							$type = $this->em->getRepository('BM2SiteBundle:EquipmentType')->find($value);
							$data['%item'.$index.'%'] = $this->links->ObjectLink($type, false, $this->absolute);
						}
						break;
					case 'house':
						$house = $this->em->getRepository('BM2SiteBundle:House')->find($value);
						$data['%house'.$index.'%'] = $this->links->ObjectLink($house, false, $this->absolute);
						break;
					case 'unit':
						$unit = $this->em->getRepository('BM2SiteBundle:Unit')->find($value);
						$data['%unit'.$index.'%'] = $this->links->ObjectLink($unit, false, $this->absolute);
						break;
					case 'assoc':
						$assoc = $this->em->getRepository('BM2SiteBundle:Association')->find($value);
						$data['%assoc'.$index.'%'] = $this->links->ObjectLink($assoc, false, $this->absolute);
						break;
					case 'deity':
						$deity = $this->em->getRepository('BM2SiteBundle:Deity')->find($value);
						$data['%deity'.$index.'%'] = $this->links->ObjectLink($deity, false, $this->absolute);
						break;
					case 'law':
						$law = $this->em->getRepository('BM2SiteBundle:Law')->find($value);
						$data['%law'.$index.'%'] = $this->links->ObjectLink($law, false, $this->absolute);
						break;
					default:
						if (is_array($value)) {
							$data[$key]=$this->translator->trans($value['key'], $value);
						} else {
							$data[$key]=$value;
						}
				}
			} elseif (preg_match('/%name-([^-]+)(-.*)?%/', $key, $matches)) {
				// translation elements, syntax %name-(type)%
				$subkey = $matches[1];
				if (isset($matches[2])) $index = $matches[2]; else $index='';
				switch ($subkey) {
					case 'card':
						$card = $this->em->getRepository('DungeonBundle:DungeonCardType')->find($value);
						$data['%card'.$index.'%'] = "<em>".$this->translator->trans('card.'.$card->getName().'.title', array(), $domain)."</em>";
						break;
					case 'distance':
						$data['%distance'.$index.'%'] = $this->geo->distanceFilter($value);
						break;
					case 'direction':
						$data['%direction'.$index.'%'] = $this->translator->trans($this->geo->directionFilter($value, true));
						break;
				}
			} else {
				$data[$key]=$value;
			}
		}
		return $data;
	}

}
