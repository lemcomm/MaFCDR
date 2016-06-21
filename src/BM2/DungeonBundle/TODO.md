Andrew's Notes for future work:

New monster sizes have been described but not implemented: "massive" "gigantic" "immense" "collosal"

Tweak the function that closes dungeons to better accommodate larger party sizes in the short term until code is added to force minimum/maximum dungeon lengths--this'll probably with the dungeon-types branch and it's incorporation to my master.

Implement shorter and longer dungeons. look at line 842 in DungeonBundle/Service/DungeonMaster.php

Incorporate biomes into dungeon locations and types; look at line 189 in SiteBundle/Service/Economy.php:

	public function checkSpecialConditions(Settlement $settlement, $building_name) {
		// special conditions - these are hardcoded because they can be complex
		switch (strtolower($building_name)) {
			case 'mine':	// only in hills and mountains with metal
				if ($settlement->getGeoData()->getHills() == false && $settlement->getGeoData()->getBiome()->getName() != 'rock') {
					return false;
				}
				$metal = $this->em->getRepository('BM2SiteBundle:ResourceType')->findOneByName("metal");
				$my_metal = $settlement->findResource($metal);
				if ($my_metal == null || $my_metal->getAmount()<=0) {
					return false;
				}
				break;
			case 'stables':	// only in grass- or scrublands
				$geo = $settlement->getGeoData()->getBiome()->getName();
				if (!in_array($geo, array('grass', 'thin grass', 'scrub', 'thin scrub'))) {
					return false;
				}
				break;
			case 'royal mews':	// only in grasslands
				$geo = $settlement->getGeoData()->getBiome()->getName();
				if (!in_array($geo, array('grass', 'thin grass'))) {
					return false;
				}
				break;
			case 'fishery':	// only at ocean or lake
				if ($settlement->getGeoData()->getCoast() == false && $settlement->getGeoData()->getLake() == false ) {
					return false;
				}
				break;
			case 'lumber yard':	// only in forests
				$geo = $settlement->getGeoData()->getBiome()->getName();
				if (!in_array($geo, array('forest', 'dense forest'))) {
					return false;
				}
				break;
			case 'irrigation ditches': // only near rivers, not in mountains or marshes
				if ($settlement->getGeoData()->getRiver() == false) {
					return false;
				}
				$geo = $settlement->getGeoData()->getBiome()->getName();
				if (in_array($geo, array('rock', 'marsh'))) {
					return false;
				}
				break;
			case 'fortress': // not in marshes
			case 'citadel': // not in marshes
				$geo = $settlement->getGeoData()->getBiome()->getName();
				if ($geo == 'marsh') {
					return false;
				}
				break;
			case 'archery range': // not in dense forest or mountains
				$geo = $settlement->getGeoData()->getBiome()->getName();
				if (in_array($geo, array('rock', 'dense forest'))) {
					return false;
				}
				break;
		}
		return true;
	}
	
Implement a movement grid system? Something with width and depth to simulate the actual movement of the dungeoneers and monsters and to make ranged attacks actually mean something?

Add more special dungeons (scenarios)! 

Add more code to support special dungeons.

Rework humanoid monsters to use experience descriptors rather than size in order to vary power ratings. Something like novice, experienced, veteran, legendary rather than small, large, giant, etc.

Make monsters use cards rather than a calculation of attack power versus defense power as found in the Fights function in the DungeonMaster service.
