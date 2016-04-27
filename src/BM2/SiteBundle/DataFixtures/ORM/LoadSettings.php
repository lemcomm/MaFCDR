<?php

namespace BM2\SiteBundle\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use BM2\SiteBundle\Entity\Setting;


class LoadSettings extends AbstractFixture implements OrderedFixtureInterface, ContainerAwareInterface {

    private $settings = array(
        'travel.bridgedistance' => 250,
        'spot.basedistance' => 1000,
        'spot.scoutmod' => 500,
        'spot.towerdistance' => 2500,
        'act.basedistance' => 250,
        'act.scoutmod' => 50,
        'cycle' => 0
    );

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * {@inheritDoc}
     */
    public function getOrder() {
        return 1;
    }

    /**
     * {@inheritDoc}
     */
    public function setContainer(ContainerInterface $container = null) {
        $this->container = $container;
    }

    /**
     * {@inheritDoc}
     */
    public function load(ObjectManager $manager) {
        $appstate = $this->container->get('appstate');
        foreach ($this->settings as $key=>$val) {
            $appstate->setGlobal($key, $val);
        }
        $manager->flush();
    }
}
