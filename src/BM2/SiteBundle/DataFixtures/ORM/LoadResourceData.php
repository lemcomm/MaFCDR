<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

use BM2\SiteBundle\Entity\ResourceType;


class LoadResourceData extends AbstractFixture implements OrderedFixtureInterface {

    private $resources = array(
        'food'          => array('gold'=>0.01),
        'wood'          => array('gold'=>0.02),
        'metal'         => array('gold'=>0.025),
        'goods'         => array('gold'=>0.1),
        'money'         => array('gold'=>0.5),
    );

    /**
     * {@inheritDoc}
     */
    public function getOrder() {
        return 1;
    }

    /**
     * {@inheritDoc}
     */
    public function load(ObjectManager $manager) {
        foreach ($this->resources as $name=>$data) {
            $type = new ResourceType();
            $type->setName($name);
            $type->setGoldValue($data['gold']);
            $manager->persist($type);
            $this->addReference('resourcetype: '.strtolower($name), $type);            
        }
        $manager->flush();
    }
}
