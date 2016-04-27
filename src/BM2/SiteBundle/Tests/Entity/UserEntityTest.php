<?php

namespace BM2\SiteBundle\Tests\Entity;

use BM2\SiteBundle\Entity\User;


class UserEntityTest extends GenericEntityTest {

	public function testSimpleData() {
		// can't use the runPropertiesTests() because the fos_user class doesn't define some setters and getters
		$user = new User;

		$this->datetimeTest($user, 'created');
		$this->numberTest($user, 'newCharsLimit');
		$this->stringTest($user, 'language');
		$this->booleanTest($user, 'notifications');
		$this->numberTest($user, 'credits');
		$this->numberTest($user, 'vipStatus');

		$this->toManyAssociationTest($user, 'characters', 'BM2\SiteBundle\Entity\Character');
		$this->toManyAssociationTest($user, 'payments', 'BM2\SiteBundle\Entity\UserPayment');
		$this->toManyAssociationTest($user, 'creditHistory', 'BM2\SiteBundle\Entity\CreditHistory');
		$this->toOneAssociationTest($user, 'currentCharacter', 'BM2\SiteBundle\Entity\Character');
		$this->toManyAssociationTest($user, 'crests', 'BM2\SiteBundle\Entity\Heraldry');
		$this->toManyAssociationTest($user, 'cultures', 'BM2\SiteBundle\Entity\Culture');
	}

}
