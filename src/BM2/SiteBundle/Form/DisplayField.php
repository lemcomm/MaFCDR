<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;

class DisplayField extends AbstractType {

    public function getParent() {
        return 'text';
    }

    public function getName() {
        return 'display';
    }
}
