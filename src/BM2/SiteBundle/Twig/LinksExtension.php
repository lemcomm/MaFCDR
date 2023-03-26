<?php

namespace BM2\SiteBundle\Twig;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\HttpFoundation\RequestStack;
use Monolog\Logger;


class LinksExtension extends \Twig_Extension {

	private $em;
	private $generator;
	private $translator;
	private $logger;
	private $request_stack; // for debugging until I've fixed the bug below where it is used

	// FIXME: type hinting for $translator removed because the addition of LoggingTranslator is breaking it
	public function __construct(EntityManager $em, UrlGeneratorInterface $generator, $translator, Logger $logger, RequestStack $rs) {
		$this->em = $em;
		$this->generator = $generator;
		$this->translator = $translator;
		$this->logger = $logger;
		$this->request_stack = $rs;
	}

	public function getFunctions() {
		return array(
			'objectlink' => new \Twig_SimpleFunction('link', array($this, 'ObjectLink'), array('is_safe' => array('html'))),
			'idnamelink' => new \Twig_SimpleFunction('*_link', array($this, 'IdNameLink'), array('is_safe' => array('html'))),
		);
	}

	public function getFilters() {
		return array(
			new \Twig_SimpleFilter('wikilinks', array($this, 'wikilinksFilter'), array('is_safe' => array('html'))),
			new \Twig_SimpleFilter('manuallinks', array($this, 'manuallinksFilter'), array('is_safe' => array('html'))),
			);
	}

	public function wikilinksFilter($input) {
		$pattern = '/\[[a-zA-Z]+:[0-9]+\]/';
		$output = preg_replace_callback($pattern, array(get_class($this), "wikilinksReplacer"), $input);
		return $output;
	}

    /** @noinspection PhpUnusedPrivateMethodInspection */
    private function wikilinksReplacer($matches) {
		$link = '';
		foreach ($matches as $match) {
			$data = explode(':', trim($match, "[]"));
			$id = $data[1];
			switch (strtolower($data[0])) {
				case 'r':
				case 'realm':
					$type = 'Realm';
					break;
				case 's':
				case 'settlement':
				case 'e':
				case 'estate':
					$type = 'Settlement';
					break;
				case 'c':
				case 'character':
				case 'n':
				case 'noble':
					$type = 'Character';
					break;
				case 'assoc':
				case 'association':
				case 'guild':
				case 'religion':
				case 'corps':
				case 'company':
				case 'temple':
				case 'order':
				case 'faith':
				case 'g':
				case 'f':
					$type = 'Association';
					break;
				case 'p':
				case 'poi':
				case 'place':
				case 'placeofinterest':
					$type = 'Place';
					break;
				case 'vote':
					$type = 'Election';
					break;
#These presently do not actually reference anything and using them will error out an entire conversation for all users.
#				case 'i':
#				case 'item':
#					$type = 'Item';
#					break;
				case 'a':
				case 'artifact':
					$type = 'Artifact';
					break;
				case 'w':
				case 'war':
					$type = 'War';
					break;
				case 'news':
				case 'newspaper':
				case 'newsedition':
				case 'edition':
				case 'pub':
				case 'publication':
					$type = 'NewsEdition';
					break;
				case 'pos':
				case 'position':
				case 'realmpos':
				case 'realmposition':
					$type = 'RealmPosition';
					break;
				case 'h':
				case 'f':
				case 'house':
				case 'dynasty':
				case 'clan':
				case 'family':
					$type = 'House';
					break;
				case 'u':
				case 'unit':
					$type = 'Unit';
					break;
				case 'conv':
				case 'topic':
				case 'conversation':
					$type = 'Conversation';
					break;
				case 'l':
				case 'law':
					$type = 'Law';
					break;
				case 'd':
				case 'deity':
				case 'god':
					$type = 'Deity';
					break;
				case 'j':
				case 'journal':
					$type = 'Journal';
					break;
				default:
					return "[<em>invalid reference</em>]";
			}
			$entity = $this->em->getRepository('BM2SiteBundle:'.$type)->find($id);
			if ($entity) {
				if ($type == 'Unit') {
					$url = $this->generator->generate($this->getLink($type), array('unit' => $id));
				} elseif ($type == 'NewsEdition') {
					$url = $this->generator->generate($this->getLink($type), array('edition' => $id));
				} elseif ($type == 'Conversation') {
					if($entity->getLocalFor()) {
						$url = $this->generator->generate('maf_conv_local');
					} else {
						$url = $this->generator->generate($this->getLink($type), array('conv' => $id));
					}
				} else {
					$url = $this->generator->generate($this->getLink($type), array('id' => $id));
				}
				if (!in_array($type, ['NewsEdition', 'Unit', 'Conversation', 'Journal'])) {
					$name = $entity->getName();
				} elseif ($type == 'Unit') {
					$name = $entity->getSettings()->getName();
				} elseif ($type == 'Conversation') {
					if ($entity->getLocalFor()) {
						$name = 'Local Conversation';
					} else {
						$name = $entity->getTopic();
					}
				} elseif ($type == 'Journal') {
					$name = $entity->getTopic();
				} else {
					$name = $entity->getPaper()->getName();
				}
				$link .= '<a href="'.$url.'">'.$name.'</a>';
			} else {
				$link = "[<em>invalid reference</em>]";
			}
		}
		return $link;
	}

	public function manuallinksFilter($input) {
		$pattern = '/\[[a-zA-Z_]+\]/';
		$output = preg_replace_callback($pattern, array(get_class($this), "manuallinksReplacer"), $input);
		return $output;
	}

	private function manuallinksReplacer($matches) {
		$link = '';
		foreach ($matches as $match) {
			// FIXME: this makes sure the translation string fits, but it should restore at least first-letter case
			$page = strtolower(trim($match, "[]"));
			$url = $this->generator->generate('bm2_manual', array('page' => $page));
			$name = $this->translator->trans("manual.".$page);
			$link .= '<a href="'.$url.'">'.$name.'</a>';
		}
		return $link;
	}

	private function getLink($name) {
		switch (strtolower($name)) {
			case 'activity':    	return 'maf_activity';
			case 'activityreport':  return 'maf_activity_report';
			case 'character':       return 'bm2_site_character_view';
			case 'settlement':      return 'bm2_settlement';
			case 'battle':    	return 'bm2_battle';
			case 'battlereport':    return 'bm2_battlereport';
			case 'realm':           return 'bm2_realm';
			case 'realmposition':   return 'bm2_position';
			case 'eventlog':        return 'bm2_eventlog';
			case 'feature':
			case 'featuretype':     return 'bm2_site_info_featuretype';
			case 'building':
			case 'buildingtype':    return 'bm2_site_info_buildingtype';
			case 'entourage':
			case 'entouragetype':   return 'bm2_site_info_entouragetype';
			case 'equipmenttype':   return 'bm2_site_info_equipmenttype';
			case 'action':		return 'bm2_actiondetails';
			case 'election':	return 'bm2_site_realm_vote';
			case 'mercenaries':	return 'bm2_mercenaries';
			case 'quest':		return 'bm2_site_quests_details';
			case 'artifact':	return 'bm2_site_artifacts_details';
			case 'war':		return 'bm2_site_war_view';
			case 'newsedition':	return 'bm2_site_news_read';
			case 'house':		return 'maf_house';
			case 'place':		return 'maf_place';
			case 'unit':		return 'maf_units_info';
			case 'conversation':	return 'maf_conv_read';
			case 'assoc':
			case 'association':	return 'maf_assoc';
			case 'law':		return 'maf_law';
			case 'deity':		return 'maf_deity';
			case 'journal':		return 'maf_journal';
		}
		return 'invalid link entity "'.$name.'", this should never happen!';
	}

	// TODO: pluralization!
	public function ObjectLink($entity, $raw=false, $absolute=false, $number=1) {
		if (!is_object($entity)) {
			$this->logger->error("link() called without object - $entity"); // fuck, it's impossible to get a backtrace! - out of memory
			$this->logger->error("dump: ".\Doctrine\Common\Util\Debug::dump($entity, 1, true, false));
			if ($this->request_stack->getCurrentRequest()) {
				$this->logger->error("request: ".$this->request_stack->getCurrentRequest()->getRequestUri());
			}
			return "[invalid object]";
		}
		$classname = implode('', array_slice(explode('\\', get_class($entity)), -1));
		$linktype = null;
		switch ($classname) {
			case 'GeoFeature':
				$entity = $entity->getType();
                // break missing, intentional
			case 'FeatureType':
				$id = $entity->getId();
				$name = $this->featurename($entity->getName());
				$linktype = 'feature';
				break;
			case 'BattleReport':
				$id = $entity->getId();
				$loc = $entity->getLocationName();
				$name = $this->translator->trans($loc['key'], array('%location%'=>$loc['name']));
				break;
			case 'ActivityReport':
				$id = $entity->getId();
				$name = $this->translator->trans('activity.'.$entity->getType()->getName().'.'.$entity->getSubType()->getName());
				$linktype = 'report';
				break;
			case 'Building':
				$entity = $entity->getType();
                // break missing, intentional
			case 'BuildingType':
				$id = $entity->getId();
				$name = $this->buildingname($entity->getName());
				$linktype = 'building';
				break;
			case 'Entourage':
				$id = $entity->getId();
				$name = $this->npcname($entity->getType()->getName(), $number);
				$linktype = 'npc';
				break;
			case 'EntourageType':
				$id = $entity->getId();
				$name = $this->npcname($entity->getName(), $number);
				$linktype = 'npc';
				break;
			case 'Action':
				$id = $entity->getId();
				$name = $this->actionname($entity->getType());
				$linktype = 'action';
				break;
			case 'Equipment':
				$entity = $entity->getType();
                // break missing, intentional
			case 'EquipmentType':
				$id = $entity->getId();
				$name = $this->equipmentname($entity->getName());
				$linktype = 'equipment';
				break;
			case 'Quest':
			case 'War':
				$id = $entity->getId();
				$name = $entity->getSummary();
				break;
			case 'NewsEdition':
				$id = $entity->getId();
				$name = $entity->getPaper->getName();
				break;
			case 'Law':
				$id = $entity->getId();
				$name = $entity->getTitle();
				break;
			case 'Journal':
				$id = $entity->getId();
				$name = $entity->getTopic();
				break;
			case 'Unit':
				$id = $entity->getId();
				$name = $entity->getSettings()->getName();
				$linktype = 'unit';
				break;
			default:
				$id = $entity->getId();
				$name = $entity->getName();
		}
		// setting link-types only for some entities:
		switch ($classname) {
			case 'Character':		$linktype = 'character'; break;
			case 'EquipmentType':		$linktype = 'equipment'; break;
			case 'RealmPosition':		$linktype = 'position'; break;
		}

		return $this->linkhelper($this->getLink($classname), $id, $name, $linktype, $raw, $absolute);
	}

	public function IdNameLink($type, $id, $name = null, $raw=false, $absolute=false) {
		$linktype = null;
		switch (strtolower($type)) {
			case 'character':
				$linktype = 'character';
				if (!$name) {
					$name = $this->em->getRepository('BM2SiteBundle:Character')->find($id)->getName();
					# Yes, this exists solely for battle reports. *sigh*
					# For the record, if you fail to declare $name, linkhelper, below, will fail out. :)
				}
				break;
			case 'geofeature':
			case 'feature':         $linktype = 'feature'; $name = $this->featurename($name); break;
			case 'building':
			case 'buildingtype':    $linktype = 'building'; $name = $this->buildingname($name); break;
			case 'entourage':
			case 'entouragetype':   $linktype = 'npc'; $name = $this->npcname($name); break;
			case 'weapon':
			case 'armour':
			case 'equipment':
			case 'equipmenttype':   $linktype = 'equipment'; $name = $this->equipmentname($name); break;
		}
		return $this->linkhelper($this->getLink($type), $id, $name, $linktype, $raw, $absolute);
	}



	private function featurename($name) {
		return $this->translator->trans("feature.".$name, array(), "economy");
	}

	private function buildingname($name) {
		return $this->translator->trans("building.".$name, array(), "economy");
	}

	private function npcname($name, $number=1) {
		return $this->translator->transchoice("npc.".$name, $number);
	}

	private function actionname($type) {
		return $this->translator->trans("queue.".$type, array(), "actions");
	}

	private function equipmentname($name) {
		return $this->translator->trans("item.".$name);
	}



	private function linkhelper($path, $id, $name, $class=null, $raw=false, $absolute=false) {
		if ($absolute) {
			$type = UrlGeneratorInterface::ABSOLUTE_URL;
		} else {
			$type = UrlGeneratorInterface::ABSOLUTE_PATH;
		}

		// FIXME: above still not working, so trying with all absolute paths now
		$type = UrlGeneratorInterface::ABSOLUTE_URL;

		if ($class === 'unit') {
			$url = $this->generator->generate($path, array('unit' => $id), $type);
		} elseif ($class === 'report') {
			$url = $this->generator->generate($path, array($class => $id), $type);
		} else {
			$url = $this->generator->generate($path, array('id' => $id), $type);
		}
		if ($raw) return $url;
		$link = '<a ';
		if (in_array($class, ['character', 'building', 'buildingtype', 'equipment', 'equipmenttype', 'weapon', 'armour', 'entouragetype', 'entourage'])) { $link .= 'class="link_'.$class.'" '; }
		$link .= 'href="'.$url.'">'.$name.'</a>';
		return $link;
	}


	public function getName() {
		return 'links_extension';
	}
}
