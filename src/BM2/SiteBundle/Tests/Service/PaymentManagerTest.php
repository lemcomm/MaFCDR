<?php

use BM2\SiteBundle\Entity\User;

class PaymentManagerTest extends \Codeception\TestCase\Test {

	protected $em;
	protected $pm;
	protected $test_user;

	function _before() {
		$this->em = $this->getModule('Symfony2')->container->get('doctrine')->getManager();
		$this->pm = $this->getModule('Symfony2')->container->get('payment_manager');
		$this->test_user = $this->em->getRepository('BM2SiteBundle:User')->findOneByUsername('user 2');
		$this->assertNotNull($this->test_user);
	}

	public function testInfo() {
		$this->assertEquals(500, $this->pm->getCostOfHeraldry());
		$this->assertEquals(200, $this->pm->calculateUserFee($this->test_user));
//		$this->assertEquals(0, $this->pm->calculateRefund($this->test_user));
	}


	public function testAccount() {
		$this->test_user->setCredits(0);
		$check = $this->pm->account($this->test_user, "test", "EUR", 10);
		$this->assertTrue($check);
		$this->assertEquals(1000, $this->test_user->getCredits());
		$payment = $this->test_user->getPayments()->last();
		$this->assertEquals("EUR", $payment->getCurrency());
		$this->assertEquals(10, $payment->getAmount());
		$this->assertEquals("test", $payment->getType());
		$this->assertEquals(1000, $payment->getCredits());

		$credit_history = $this->test_user->getCreditHistory();
		$this->assertNotNull($credit_history);
		$this->assertInstanceOf("\Doctrine\ORM\PersistentCollection", $credit_history);
		$cred = $credit_history->last();
		$this->assertEquals(1000, $cred->getCredits());
		$this->assertEquals("test", $cred->getType());

	}

	public function testSpending() {
		$this->test_user->setCredits(1000);

		$check = $this->pm->spend($this->test_user, "spending test", 200);
		$this->assertTrue($check);
		$this->assertEquals(800, $this->test_user->getCredits());

		$credit_history = $this->test_user->getCreditHistory();
		$this->assertNotNull($credit_history);
		$this->assertInstanceOf("\Doctrine\ORM\PersistentCollection", $credit_history);
		$cred = $credit_history->last();
		$this->assertEquals(-200, $cred->getCredits());
		$this->assertEquals("spending test", $cred->getType());

		$check = $this->pm->spend($this->test_user, "excessive", 2000);
		$this->assertFalse($check);
		$this->assertEquals(800, $this->test_user->getCredits());

	}

	public function testCodes() {
		$code = $this->pm->createCode(1200, 10);
		$this->assertInstanceOf("BM2\SiteBundle\Entity\Code", $code);
		$this->assertEquals($code->getCredits(), 1200);

		$old = $this->test_user->getCredits();
		$this->pm->redeemCode($this->test_user, $code);

		$this->assertEquals($this->test_user->getCredits(), $old+1200);
		$this->assertEquals($this->test_user->getVipStatus(), 10);
	}

}
