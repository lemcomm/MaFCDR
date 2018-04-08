<?php
// src/Acme/UserBundle/AcmeUserBundle.php

namespace BM2\UserBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class UserBundle extends Bundle
{
    public function getParent()
    {
        return 'FOSUserBundle';
    }
}
