<?php

namespace BM2\SiteBundle\Twig;

use BM2\SiteBundle\Service\ActivityDispatcher;


class ActivityDispatcherExtension extends \Twig_Extension implements \Twig\Extension\GlobalsInterface {
    protected $dispatcher;

    public function __construct(ActivityDispatcher $dispatcher) {
        $this->dispatcher = $dispatcher;
    }

    public function getGlobals() {
        return array(
            'activityDispatcher' => $this->dispatcher
        );
    }

    public function getName() {
        return 'activityDispatcher';
    }

}
