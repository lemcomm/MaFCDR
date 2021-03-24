<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Collections\ArrayCollection;

use BM2\SiteBundle\Entity\BuildingType;
use BM2\SiteBundle\Entity\BuildingResource;


class LoadBuildingData extends AbstractFixture implements OrderedFixtureInterface {

	private $buildings = array(
		'Academy'               => array('auto' =>      0, 'min' =>   9000, 'work' =>  25000, 'ratio' => 12000, 'builtin' => array('city'), 'requires' => array('University','Garrison','Mason'), 'icon'=>'buildings/academy.png'),
		'Alchemist'             => array('auto' =>   4000, 'min' =>   1000, 'work' =>  12000, 'ratio' =>  5000, 'builtin' => array('city'), 'requires' => array('Apothecary'), 'defenses' => 5, 'icon'=>'buildings/alchemist.png'),
		'Archery Range'         => array('auto' =>      0, 'min' =>    400, 'work' =>   6000, 'ratio' =>  3000, 'builtin' => array('city'), 'requires' => array('Bowyer','Training Ground'), 'conditions'=>true),
		'Archery School'        => array('auto' =>      0, 'min' =>    600, 'work' =>  10000, 'ratio' =>  2000, 'builtin' => array('city'), 'requires' => array('Archery Range', 'Carpenter')),
		'Armourer'              => array('auto' =>   5000, 'min' =>    900, 'work' =>  10000, 'ratio' =>  1500, 'builtin' => array('city'), 'requires' => array('Leather Tanner','Blacksmith'), 'icon'=>'buildings/armorer.png'),
		'Armoury'               => array('auto' =>      0, 'min' =>   1000, 'work' =>  10000, 'ratio' =>  4000, 'builtin' => array('city','fort'), 'requires' => array('Armourer','Weaponsmith')),
		'Bank'                  => array('auto' =>  40000, 'min' =>   4000, 'work' =>  20000, 'ratio' =>  2000, 'builtin' => array('city'), 'requires' => array('Merchants Quarter','Library','Temple','Mason')),
		'Barracks'              => array('auto' =>      0, 'min' =>    300, 'work' =>  12000, 'ratio' =>  4000, 'builtin' => array('city','fort'), 'requires' => array('Guardhouse')),
		'Blacksmith'            => array('auto' =>   1500, 'min' =>    250, 'work' =>  10000, 'ratio' =>  1000, 'builtin' => array('city','fort','port'), 'icon'=>'rpg_map/blacksmith.svg'),
		'Bladesmith'            => array('auto' =>  25000, 'min' =>   1500, 'work' =>  16000, 'ratio' =>  1800, 'builtin' => array('city'), 'requires' => array('Weaponsmith', 'Mason', 'Alchemist')),
		'Bowyer'                => array('auto' =>   2000, 'min' =>    200, 'work' =>   8000, 'ratio' =>   600, 'builtin' => array('city')),
		'Carpenter'             => array('auto' =>    600, 'min' =>     80, 'work' =>   9000, 'ratio' =>   400, 'builtin' => array('city','fort','port'), 'icon'=>'buildings/carpenter.png'),
		'Citadel'               => array('auto' =>      0, 'min' =>   6000, 'work' =>1500000, 'ratio' =>  5000, 'builtin' => array('city','fort'), 'defenses' => 70, 'requires' => array('Fortress','Bladesmith','Master Mason'), 'conditions'=>true),
		'City Hall'             => array('auto' =>  50000, 'min' =>   5000, 'work' =>  25000, 'ratio' =>  3500, 'builtin' => array('city'), 'requires' => array('Town Hall')),
		'Dirt Streets'          => array('auto' =>    400, 'min' =>     50, 'work' =>   5000, 'ratio' =>  1000, 'builtin' => array('city','port')),
		'Fairground'            => array('auto' =>  10000, 'min' =>   1200, 'work' =>  10000, 'ratio' =>  1000, 'builtin' => array('city'), 'requires' => array('Carpenter','Market')),
		'Fortress'              => array('auto' =>      0, 'min' =>   4000, 'work' => 500000, 'ratio' =>  4000, 'builtin' => array('city','fort'), 'defenses' => 50, 'requires' => array('Paved Streets','Armoury','Stone Castle','Mason'), 'conditions'=>true),
		'Garrison'              => array('auto' =>      0, 'min' =>    400, 'work' =>  15000, 'ratio' =>  8000, 'builtin' => array('city','fort'), 'requires' => array('Carpenter','Barracks')),
		'Great Temple'          => array('auto' =>  50000, 'min' =>   5000, 'work' =>  50000, 'ratio' =>  3000, 'builtin' => array('city'), 'requires' => array('University','Temple','Master Mason','Paved Streets')),
		'Guardhouse'            => array('auto' =>      0, 'min' =>    200, 'work' =>   8000, 'ratio' =>  5000, 'builtin' => array('city','fort'), 'requires' => array('Training Ground')),
		'Heavy Armourer'        => array('auto' =>  40000, 'min' =>   4000, 'work' =>  12000, 'ratio' =>  4000, 'builtin' => array('city'), 'requires' => array('Armourer')),
		'Inn'			=> array('auto' =>   1200, 'min' =>    400, 'work' =>   6000, 'ratio' =>   500, 'builtin' => array('city'), 'requires' => array('Tavern', 'Market')),
		'Leather Tanner'        => array('auto' =>   2000, 'min' =>    300, 'work' =>   8000, 'ratio' =>  1500, 'builtin' => array('city')),
		'Library'               => array('auto' =>   6000, 'min' =>    700, 'work' =>  15000, 'ratio' =>  5000, 'builtin' => array('city'), 'requires' => array('School','Temple')),
		'Market'                => array('auto' =>   2500, 'min' =>    400, 'work' =>   5000, 'ratio' =>   750, 'builtin' => array('city'), 'requires' => array('Dirt Streets'), 'icon'=>'buildings/market.png'),
		'Mason'                 => array('auto' =>    800, 'min' =>    200, 'work' =>   9000, 'ratio' =>   400, 'builtin' => array('city','fort','port'), 'icon'=>'buildings/mason.png'),
		'Master Mason'          => array('auto' => 100000, 'min' =>   4000, 'work' =>  12000, 'ratio' =>  4000, 'builtin' => array('city'), 'requires' => array('University','Mason'), 'icon'=>'buildings/mastermason.png'),
		'Merchants Quarter'     => array('auto' =>  60000, 'min' =>   3000, 'work' =>  25000, 'ratio' =>  1200, 'builtin' => array('city'), 'requires' => array('Paved Streets','Fairground','Warehouse','Mason','Tailor')),
		'Mill'  		=> array('auto' =>    500, 'min' =>    200, 'work' =>  14000, 'ratio' =>  2500, 'builtin' => array('city'), 'icon'=>'rpg_map/windmill.svg', 'icon'=>'buildings/mill.png'),
		'Mine'                  => array('auto' =>      0, 'min' =>    100, 'work' =>  20000, 'ratio' =>   400, 'builtin' => array('city'), 'requires' => array('Blacksmith','Carpenter'), 'conditions'=>true, 'icon'=>'rpg_map/mine.svg'),
		'Palisade'              => array('auto' =>   3000, 'min' =>    400, 'work' =>  25000, 'ratio' =>  1000, 'builtin' => array('city','fort','port'), 'defenses' => 10),
		'Paved Streets'         => array('auto' =>   5000, 'min' =>    500, 'work' =>  20000, 'ratio' =>  1000, 'builtin' => array('city','port'), 'requires' => array('Dirt Streets','Mason')),
		'Royal Mews'            => array('auto' =>      0, 'min' =>   5000, 'work' =>  25000, 'ratio' =>  2500, 'builtin' => array('city'), 'requires' => array('Armourer', 'Saddler', 'Training Ground'), 'conditions'=>true),
		'Saddler'               => array('auto' =>   2600, 'min' =>    250, 'work' =>   8000, 'ratio' =>   800, 'builtin' => array('city'), 'requires' => array('Leather Tanner')),
		'School'                => array('auto' =>   3000, 'min' =>    300, 'work' =>  15000, 'ratio' =>  3000, 'builtin' => array('city')),
		'Shrine'                => array('auto' =>    500, 'min' =>     50, 'work' =>   5000, 'ratio' =>  4000, 'builtin' => array('city','fort','port','temple','graveyard')),
		'Stables'               => array('auto' =>      0, 'min' =>    200, 'work' =>  10000, 'ratio' =>  1500, 'builtin' => array('city'), 'requires' => array('Saddler'), 'conditions'=>true, 'icon'=>'rpg_map/stables.svg'),
		'Stone Castle'          => array('auto' =>      0, 'min' =>   2000, 'work' => 300000, 'ratio' =>  1600, 'builtin' => array('city','fort'), 'defenses' => 5, 'requires' => array('Stone Wall','Stone Towers','Wood Castle','Dirt Streets','Mason'), 'icon'=>'rpg_map/fortress.svg'),
		'Stone Towers'          => array('auto' =>  50000, 'min' =>   1500, 'work' => 160000, 'ratio' =>  2000, 'builtin' => array('city','fort','port'), 'defenses' => 5, 'requires' => array('Wood Towers','Stone Wall','Mason'), 'icon'=>'rpg_map/tower_square.svg'),
		'Stone Wall'            => array('auto' =>  25000, 'min' =>   1000, 'work' => 120000, 'ratio' =>  2000, 'builtin' => array('city','fort','port'), 'defenses' => 10, 'requires' => array('Wood Wall','Mason')),
		'Tailor'                => array('auto' =>    300, 'min' =>     30, 'work' =>   8000, 'ratio' =>   250, 'builtin' => array('city','port')),
		'Tavern' 		=> array('auto' =>    250, 'min' =>    100, 'work' =>   4000, 'ratio' =>   400, 'builtin' => array('city','port')),
		'Temple'                => array('auto' =>   2400, 'min' =>    200, 'work' =>  20000, 'ratio' =>  2500, 'builtin' => array('city','port','temple'), 'requires' => array('Shrine','Mason')),
		'Town Hall'             => array('auto' =>   5000, 'min' =>   1000, 'work' =>  20000, 'ratio' =>  2500, 'builtin' => array('city'), 'requires' => array('Carpenter','Garrison','Mason'), 'icon'=>'rpg_map/townhall.svg'),
		'Training Ground'       => array('auto' =>      0, 'min' =>     60, 'work' =>   6000, 'ratio' =>  3500, 'builtin' => array('city','fort')),
		'University'            => array('auto' =>  80000, 'min' =>   8000, 'work' =>  30000, 'ratio' => 10000, 'builtin' => array('city'), 'requires' => array('Paved Streets','Library','Alchemist','Mason')),
		'Weaponsmith'           => array('auto' =>   5000, 'min' =>    600, 'work' =>  10000, 'ratio' =>  2000, 'builtin' => array('city'), 'requires' => array('Blacksmith')),
		'Wood Castle'           => array('auto' =>      0, 'min' =>   1200, 'work' =>  80000, 'ratio' =>  1200, 'builtin' => array('city','fort'), 'defenses' => 5, 'requires' => array('Carpenter','Wood Wall','Wood Towers'), 'icon'=>'rpg_map/fort.svg'),
		'Wood Towers'           => array('auto' =>  10000, 'min' =>   1000, 'work' =>  50000, 'ratio' =>  1200, 'builtin' => array('city','fort','port'), 'defenses' => 5, 'requires' => array('Carpenter','Wood Wall')),
		'Wood Wall'             => array('auto' =>   6000, 'min' =>    800, 'work' =>  40000, 'ratio' =>  1200, 'builtin' => array('city','fort','port'), 'defenses' => 10, 'requires' => array('Carpenter','Palisade')),

		'Fishery'           	=> array('auto' =>   1800, 'min' =>    500, 'work' =>   6000, 'ratio' =>   800, 'builtin' => array('city'), 'requires' => array('Carpenter','Blacksmith','Dirt Streets'), 'conditions'=>true),
		'Lumber Yard'           => array('auto' =>      0, 'min' =>    600, 'work' =>   8000, 'ratio' =>  1400, 'builtin' => array('city'), 'requires' => array('Carpenter','Blacksmith','Dirt Streets'), 'conditions'=>true),
		'Irrigation Ditches'    => array('auto' =>   3000, 'min' =>    200, 'work' =>  15000, 'ratio' =>   500, 'builtin' => array('city'), 'requires' => array('Carpenter','Blacksmith'), 'conditions'=>true),

		'Local Seat'		=> array('auto' =>	0, 'min' =>    100, 'work' =>   5000, 'ratio' =>   500, 'builtin' => array('city'), 'defenses' => 5, 'requires' => array('Carpenter','Dirt Streets','Wood Wall'), 'conditions'=>true),
		'Regional Seat'		=> array('auto' =>	0, 'min' =>   1000, 'work' => 500000, 'ratio' =>   500, 'builtin' => array('city'), 'defenses' => 5, 'requires' => array('Local Seat','Mason','Blacksmith','Paved Streets','Inn','School','Town Hall','Guardhouse','Wood Castle'), 'conditions'=>true),
		'Royal Seat'		=> array('auto' =>	0, 'min' =>   5000, 'work' =>1000000, 'ratio' =>   500, 'builtin' => array('city'), 'defenses' => 5, 'requires' => array('Regional Seat','Stone Castle','Library','Saddler','Barracks','Bank'), 'conditions'=>true),
		'Imperial Seat'		=> array('auto' =>	0, 'min' =>  10000, 'work' =>2000000, 'ratio' =>   500, 'builtin' => array('city'), 'defenses' => 5, 'requires' => array('Royal Seat','Fortress','Master Mason','Academy'), 'conditions'=>true),

		'Arena'			=> array('auto' =>      0, 'min' =>   4200, 'work' =>  39000, 'ratio' =>  2000, 'builtin' => array('city'), 'requires' => array('Carpenter','Training Ground','Blacksmith','Mason')),
		'Apothecary'            => array('auto' =>   2000, 'min' =>    500, 'work' =>   6000, 'ratio' =>  5000, 'builtin' => array('city','fort','port'), 'defenses' => 5),
		'Guild District'	=> array('auto' =>  60000, 'min' =>   3000, 'work' =>  25000, 'ratio' =>  1200, 'builtin' => array('city'), 'requires' => array('Paved Streets','Fairground','Guild Square','Warehouse','Tailor','Library','Inn')),
		'Race Track'		=> array('auto' =>      0, 'min' =>   7500, 'work' =>  96000, 'ratio' =>  1500, 'builtin' => array('city'), 'requires' => array('Bank','Stables')),
		'Warehouse'		=> array('auto' =>   2000, 'min' =>    250, 'work' =>   5000, 'ratio' =>  3000, 'builtin' => array('city','port'), 'requires' => array('Dirt Streets','Carpenter')),
		'Dockyard'		=> array('auto' =>   3000, 'min' =>    750, 'work' =>  10000, 'ratio' =>  2500, 'builtin' => array('city','port'), 'requires' => array('Warehouse','Mason','Inn'), 'conditions'=>true),
		'Guild Square'		=> array('auto' =>      0, 'min' =>    500, 'work' =>   7500, 'ratio' =>  3000, 'builtin' => array('city','port','fort'), 'requires' => array('Carpenter','Mason')),
		'Empty Moat'		=> array('auto' =>      0, 'min' =>    100, 'work' =>  10000, 'ratio' =>   400, 'builtin' => array('city','fort'), 'defenses' => 5, 'requires' => array('Palisade')),
		'Filled Moat'		=> array('auto' =>      0, 'min' =>    100, 'work' =>  10000, 'ratio' =>   400, 'builtin' => array('city','fort'), 'defenses' => 5, 'requires' => array('Empty Moat'), 'conditions'=>true),
		'Quarry'		=> array('auto' =>      0, 'min' =>    200, 'work' =>  15000, 'ratio' =>   500, 'builtin' => array('city'), 'requires' => array('Carpenter'), 'conditions'=>true),
		
		'Hunters Lodge'		=> array('auto' =>	0, 'min' =>	50, 'work' =>	5000, 'ratio' =>  1200, 'builtin' => ['city', 'fort'], 'requires' => ['Bowyer'], 'conditions'=>true),
	);

	private $resources = array(
		'Academy'               => array('wood'=>array('construction'=>12000), 'metal'=>array('construction'=>1500), 'goods'=>array('construction'=>200, 'operation'=>15), 'money'=>array('construction'=>100, 'operation'=>5)),
		'Alchemist'             => array('wood'=>array('construction'=>2500), 'metal'=>array('construction'=>500), 'goods'=>array('construction'=>100, 'operation'=>5, 'bonus'=>1), 'money'=>array('construction'=>150, 'operation'=>5, 'bonus'=>3)),
		'Archery Range'         => array('wood'=>array('construction'=>1200, 'operate'=>5), 'metal'=>array('construction'=>250, 'operate'=>5)),
		'Archery School'        => array('wood'=>array('construction'=>1600, 'operate'=>5), 'metal'=>array('construction'=>400, 'operate'=>5)),
		'Armourer'              => array('wood'=>array('construction'=>4000, 'operation'=>15), 'metal'=>array('construction'=>1500, 'operation'=>100), 'goods'=>array('provides'=>2)),
		'Armoury'               => array('wood'=>array('construction'=>3500), 'metal'=>array('construction'=>1200)),
		'Bank'                  => array('wood'=>array('construction'=>3000), 'metal'=>array('construction'=>500), 'goods'=>array('construction'=>1000), 'money'=>array('provides'=>20, 'bonus'=>6)),
		'Barracks'              => array('wood'=>array('construction'=>3000), 'metal'=>array('construction'=>400)),
		'Blacksmith'            => array('wood'=>array('construction'=>3000, 'operation'=>25), 'metal'=>array('construction'=>800, 'operation'=>80), 'goods'=>array('provides'=>8)),
		'Bladesmith'            => array('wood'=>array('construction'=>6000, 'operation'=>30), 'metal'=>array('construction'=>2500, 'operation'=>120), 'goods'=>array('operation'=>2)),
		'Bowyer'                => array('wood'=>array('construction'=>1800, 'operate'=>50), 'metal'=>array('construction'=>300, 'operate'=>10)),
		'Carpenter'             => array('wood'=>array('construction'=>2000), 'metal'=>array('construction'=>250), 'goods'=>array('provides'=>5)),
		'Citadel'               => array('wood'=>array('construction'=>25000), 'metal'=>array('construction'=>8000), 'goods'=>array('construction'=>1500, 'operation'=>10), 'money'=>array('construction'=>2500, 'operation'=>100)),
		'City Hall'             => array('wood'=>array('construction'=>10000), 'metal'=>array('construction'=>500), 'goods'=>array('construction'=>500, 'operation'=>2), 'money'=>array('construction'=>500, 'operation'=>50, 'bonus'=>10)),
		'Dirt Streets'          => array('goods'=>array('bonus'=>5), 'money'=>array('bonus'=>2)),
		'Fairground'            => array('food'=>array('bonus'=>5),'wood'=>array('construction'=>1000, 'bonus'=>5), 'metal'=>array('construction'=>100, 'bonus'=>5), 'goods'=>array('provides'=>10, 'bonus'=>10), 'money'=>array('provides'=>10, 'bonus'=>2)),
		'Fortress'              => array('wood'=>array('construction'=>20000), 'metal'=>array('construction'=>4000), 'goods'=>array('construction'=>1000, 'operation'=>5), 'money'=>array('construction'=>1000, 'operation'=>50)),
		'Garrison'              => array('wood'=>array('construction'=>4000), 'metal'=>array('construction'=>650)),
		'Great Temple'          => array('wood'=>array('construction'=>8000), 'metal'=>array('construction'=>1000), 'money'=>array('operation'=>25, 'bonus'=>8)),
		'Guardhouse'            => array('wood'=>array('construction'=>2500), 'metal'=>array('construction'=>250)),
		'Heavy Armourer'        => array('wood'=>array('construction'=>6000, 'operation'=>20), 'metal'=>array('construction'=>2500, 'operation'=>150), 'goods'=>array('provides'=>2)),
		'Inn' 			=> array('wood'=>array('construction'=>1500), 'metal'=>array('construction'=>50), 'goods'=>array('construction'=>100, 'operation'=>4)),
		'Leather Tanner'        => array('wood'=>array('construction'=>2000), 'metal'=>array('construction'=>200), 'goods'=>array('provides'=>10)),
		'Library'               => array('wood'=>array('construction'=>2000), 'metal'=>array('construction'=>100), 'money'=>array('construction'=>250, 'operation'=>5)),
		'Market'                => array('food'=>array('bonus'=>5), 'wood'=>array('construction'=>500, 'bonus'=>5), 'metal'=>array('bonus'=>5), 'goods'=>array('provides'=>10, 'bonus'=>10)),
		'Mason'                 => array('wood'=>array('construction'=>2500), 'metal'=>array('construction'=>300)),
		'Master Mason'          => array('wood'=>array('construction'=>4000), 'metal'=>array('construction'=>500), 'money'=>array('construction'=>100, 'operation'=>25)),
		'Merchants Quarter'     => array('wood'=>array('construction'=>3500), 'goods'=>array('construction'=>400, 'provides'=>10, 'bonus'=>10), 'money'=>array('construction'=>200, 'provides'=>20, 'bonus'=>12)),
		'Mill'                  => array('food'=>array('bonus'=>10), 'wood'=>array('construction'=>2000), 'metal'=>array('construction'=>200)),
		'Mine'                  => array('wood'=>array('construction'=>4000), 'metal'=>array('bonus'=>50), 'goods'=>array('construction'=>200, 'operation'=>5), 'money'=>array('bonus'=>12)),
		'Palisade'              => array('wood'=>array('construction'=>3000)),
		'Paved Streets'         => array('goods'=>array('bonus'=>5), 'money'=>array('bonus'=>3)),
		'Royal Mews'            => array('food'=>array('operation'=>150),'wood'=>array('construction'=>6000), 'metal'=>array('construction'=>500)),
		'Saddler'               => array('wood'=>array('construction'=>2500), 'metal'=>array('construction'=>400), 'goods'=>array('provides'=>2)),
		'School'                => array('wood'=>array('construction'=>2500), 'metal'=>array('construction'=>200), 'goods'=>array('operation'=>5), 'money'=>array('construction'=>100, 'operation'=>5)),
		'Shrine'                => array('wood'=>array('construction'=>1000), 'metal'=>array('construction'=>20), 'money'=>array('operation'=>10, 'bonus'=>2)),
		'Stables'               => array('food'=>array('operation'=>100), 'wood'=>array('construction'=>3500), 'metal'=>array('construction'=>200)),
		'Stone Castle'          => array('wood'=>array('construction'=>6000), 'metal'=>array('construction'=>3000), 'goods'=>array('construction'=>400), 'money'=>array('construction'=>500, 'operation'=>50)),
		'Stone Towers'          => array('wood'=>array('construction'=>3000), 'metal'=>array('construction'=>2000), 'goods'=>array('construction'=>200), 'money'=>array('construction'=>250, 'operation'=>20)),
		'Stone Wall'            => array('wood'=>array('construction'=>4000), 'metal'=>array('construction'=>1000), 'money'=>array('construction'=>100)),
		'Tailor'                => array('wood'=>array('construction'=>1500), 'metal'=>array('construction'=>50, 'operation'=>1), 'goods'=>array('provides'=>10)),
		'Tavern'		=> array('wood'=>array('construction'=>1000), 'metal'=>array('construction'=>20), 'goods'=>array('construction'=>50, 'operation'=>2)),
		'Temple'                => array('wood'=>array('construction'=>3000), 'metal'=>array('construction'=>200), 'money'=>array('operation'=>25, 'bonus'=>4)),
		'Town Hall'             => array('wood'=>array('construction'=>5000), 'metal'=>array('construction'=>300), 'money'=>array('construction'=>100, 'operation'=>50, 'bonus'=>7)),
		'Training Ground'       => array('wood'=>array('construction'=>500), 'metal'=>array('construction'=>200)),
		'University'            => array('wood'=>array('construction'=>15000), 'metal'=>array('construction'=>1000), 'goods'=>array('construction'=>250, 'operation'=>10), 'money'=>array('construction'=>120, 'operation'=>10)),
		'Weaponsmith'           => array('wood'=>array('construction'=>4000, 'operation'=>25), 'metal'=>array('construction'=>2000, 'operation'=>100), 'goods'=>array('provides'=>1)),
		'Wood Castle'           => array('wood'=>array('construction'=>6000), 'metal'=>array('construction'=>800), 'goods'=>array('construction'=>150), 'money'=>array('construction'=>200, 'operation'=>40)),
		'Wood Towers'           => array('wood'=>array('construction'=>4000), 'metal'=>array('construction'=>200), 'goods'=>array('construction'=>50), 'money'=>array('construction'=>100, 'operation'=>10)),
		'Wood Wall'             => array('wood'=>array('construction'=>5000), 'metal'=>array('construction'=>100)),

		'Fishery'               => array('wood'=>array('construction'=>800), 'metal'=>array('construction'=>100), 'goods'=>array('construction'=>50), 'food'=>array('provides'=>50, 'bonus'=>5)),
		'Lumber Yard'           => array('wood'=>array('construction'=>500, 'bonus'=>4), 'metal'=>array('construction'=>100, 'operation'=>1)),
		'Irrigation Ditches'    => array('wood'=>array('construction'=>600), 'metal'=>array('construction'=>25), 'goods'=>array('construction'=>20), 'food'=>array('provides'=>100, 'bonus'=>1)),

		'Local Seat'		=> array('wood'=>array('construction'=>2000, 'operation'=>10), 'metal'=>array('construction'=>300), 'money'=>array('construction'=>100, 'provides'=>2, 'bonus'=>1), 'food'=>array('bonus'=>3)),
		'Regional Seat'		=> array('wood'=>array('construction'=>8000, 'operation'=>20), 'metal'=>array('construction'=>1000, 'bonus'=>5), 'money'=>array('construction'=>1000, 'provides'=>2, 'bonus'=>3), 'food'=>array('bonus'=>3)),
		'Royal Seat'		=> array('wood'=>array('construction'=>14000, 'operation'=>70), 'metal'=>array('construction'=>1750, 'bonus'=>10), 'money'=>array('construction'=>1750, 'operation'=>3, 'bonus'=>6), 'food'=>array('bonus'=>4)),
		'Imperial Seat'		=> array('wood'=>array('construction'=>20000, 'operation'=>100), 'metal'=>array('construction'=>2500, 'bonus'=>15), 'money'=>array('construction'=>2500, 'operation'=>7, 'bonus'=>12), 'food'=>array('bonus'=>10)),

		'Arena'			=> array('wood'=>array('construction'=>6000, 'operation'=>20), 'metal'=>array('construction'=>1000, 'operation'=>75), 'goods'=>array('construction'=>300), 'money'=>array('construction'=>500, 'provides'=>5, 'bonus'=>5)),
		'Apothecary'            => array('wood'=>array('construction'=>1250), 'metal'=>array('construction'=>250), 'goods'=>array('construction'=>50, 'operation'=>2, 'bonus'=>3), 'money'=>array('construction'=>100, 'operation'=>2, 'bonus'=>1)),
		'Guild District'	=> array('wood'=>array('construction'=>3500), 'goods'=>array('construction'=>400, 'provides'=>10, 'bonus'=>10), 'money'=>array('construction'=>200, 'provides'=>20, 'bonus'=>12)),
		'Race Track'		=> array('wood'=>array('construction'=>4000, 'operation'=>20), 'metal'=>array('construction'=>500, 'operation'=>10), 'goods'=>array('construction'=>200), 'money'=>array('construction'=>2000, 'provides'=>5, 'bonus'=>5)),
		'Warehouse'		=> array('wood'=>array('construction'=>2000), 'metal'=>array('construction'=>100), 'goods'=>array('construction'=>25, 'bonus'=>10), 'money'=>array('operation'=>5, 'bonus'=>3), 'food'=>array('bonus'=>2)),
		'Dockyard'		=> array('wood'=>array('construction'=>2500), 'metal'=>array('construction'=>250), 'goods'=>array('construction'=>500, 'bonus'=>5), 'money'=>array('operation'=>10, 'bonus'=>10), 'food'=>array('bonus'=>3)),
		'Guild Square'		=> array('wood'=>array('construction'=>1000), 'goods'=>array('construction'=>100, 'provides'=>2, 'bonus'=>2), 'money'=>array('construction'=>200, 'provides'=>5, 'bonus'=>4)),
		'Empty Moat'		=> array('wood'=>array('construction'=>500), 'metal'=>array('construction'=>50), 'goods'=>array('construction'=>300, 'operation'=>2)),
		'Filled Moat'		=> array('wood'=>array('construction'=>5000), 'metal'=>array('construction'=>200), 'goods'=>array('construction'=>300, 'operation'=>3)),
		'Quarry'		=> array('wood'=>array('construction'=>3000, 'operation'=>100), 'metal'=>array('construction'=>1000, 'operation'=>50, 'bonus'=>20), 'goods'=>array('construction'=>300, 'operation'=>2, 'bonus'=>1)),
		
		'Hunters Lodge'         => array('wood'=>array('construction'=>1800, 'bonus'=>1), 'metal'=>array('construction'=>100), 'food'=>array('provides'=>5, 'bonus'=>5)),
	);

	/**
	 * {@inheritDoc}
	 */
	public function getOrder() {
		return 10; // requires resourcedata and settlementdata
	}

	/**
	 * {@inheritDoc}
	 */
	public function load(ObjectManager $manager) {
		$all = new ArrayCollection();
		foreach ($this->buildings as $name=>$data) {
			$type = $manager->getRepository('BM2SiteBundle:BuildingType')->findOneByName($name);
			if (!$type) {
				$type = new BuildingType();
				$manager->persist($type);
			}
			$type->setName($name);
			$type->setBuildHours($data['work']);
			$type->setAutoPopulation($data['auto'])->setMinPopulation($data['min']);
			$type->setBuiltIn($data['builtin']);
			$type->setPerPeople($data['ratio']);
			$type->setDefenses(isset($data['defenses'])?$data['defenses']:0);
			$type->setSpecialConditions(isset($data['conditions'])?true:false);
			if (isset($data['icon'])) {
				$type->setIcon($data['icon']);
			}
			$all->add($type);
			$this->addReference('buildingtype: '.strtolower($name), $type);

			foreach ($this->resources[$name] as $resourcename => $resourcedata) {
				$rt = $manager->getRepository('BM2SiteBundle:ResourceType')->findOneByName($resourcename);
				if (!$rt) {
					echo "can't find $resourcename needed by $name.\n";
				}
				$br = null;
				foreach ($type->getResources() as $r) {
					if ($r->getResourceType() == $rt) {
						$br = $r;
						break;
					}
				}
				if (!$br) {
					$br = new BuildingResource;
					$manager->persist($br);
				}
				$br->setBuildingType($type);
				$br->setResourceType($rt);
				$br->setRequiresConstruction(isset($resourcedata['construction'])?$resourcedata['construction']:0);
				$br->setRequiresOperation(isset($resourcedata['operation'])?$resourcedata['operation']:0);
				$br->setProvidesOperation(isset($resourcedata['provides'])?$resourcedata['provides']:0);
				$br->setProvidesOperationBonus(isset($resourcedata['bonus'])?$resourcedata['bonus']:0);
			}
		}
		foreach ($this->buildings as $name=>$data) {
			if (isset($data['requires'])) {
				$me = $all->filter(function($type) use ($name) {
					return $type->getName() == $name;
				})->first();
				foreach ($me->getRequires() as $requirement) {
					$me->removeRequire($requirement);
				}
				foreach ($data['requires'] as $requires) {
					$enabler = $all->filter(function($type) use ($requires) {
						return $type->getName() == $requires;
					})->first();
					if ($enabler) {
						if (!$me->getRequires()->contains($enabler)) {
							$me->getRequires()->add($enabler);
						}
					} else {
						echo "can't find $requires needed by $name.\n";
					}
				}
			}
		}
		/* Old code for when we wanted to have buildings buildable in multiple settlement types.
		Might be usable later, so it remains. --Andrew, 20171123
		foreach ($this->buildings as $name=>$data) {
			if (isset($data['builtin'])) {
				$me = $all->filter(function($type) use ($name) {
					return $type->getName() == $name;
				})->first();
				foreach ($me->getBuiltIns() as $settlementtype) {
					$me->removeBuiltIn($settlementtype);
				}
				foreach ($data['builtin'] as $settlementtype) {
					$builtin = $this->getReference('settlementtype: '.strtolower($settlementtype));
					if ($builtin) {
						foreach ($builtin as $settlement) {
							if (!$me->getBuiltIn()->contains($settlement)) {
								$type->addBuiltIn($settlement);
							}
						}
					} else {
						echo "can't find settlement type(s) needed by $name.\n";
					}
				}
			}
		}
		*/
		$manager->flush();
	}

}
