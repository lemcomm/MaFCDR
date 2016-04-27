<?php


class NewsManagerTest extends \Codeception\TestCase\Test {

	protected $em;
	protected $news;
	protected $alice;

	function _before() {
		$this->em = $this->getModule('Symfony2')->container->get('doctrine')->getManager();
		$this->news = $this->getModule('Symfony2')->container->get('news_manager');

		$this->alice = $this->em->getRepository('BM2SiteBundle:Character')->findOneByName('Alice Kepler');
		$this->assertInstanceOf("BM2\SiteBundle\Entity\Character", $this->alice, "test character not found");
	}


	public function testCreate() {
		$paper = $this->news->newPaper('Test Publication', $this->alice);

		$this->assertInstanceOf("BM2\SiteBundle\Entity\NewsPaper", $paper);
		$this->assertEquals($paper->getName(), 'Test Publication');

		$edition = $this->news->newEdition($paper);
		$this->assertInstanceOf("BM2\SiteBundle\Entity\NewsEdition", $edition);
		$this->assertNull($edition->getPublished());
		$this->em->flush(); // because the test below uses DQL
		$this->assertNotEquals($this->news->latestEdition($paper), $edition);

		$this->news->publishEdition($edition);
		$this->assertNotNull($edition->getPublished());
		$this->em->flush(); // because the test below uses DQL
		$this->assertEquals($this->news->latestEdition($paper), $edition);
	}


}
