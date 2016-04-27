<?php

namespace BM2\SiteBundle\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Bundle\FrameworkBundle\Client;

use BM2\SiteBundle\Tests\IntegrationTestCase;
use BM2\SiteBundle\Command\TurnCommand;


class CommandTurnTest extends IntegrationTestCase {

	public function runCommand(Client $client, $command) {
		$application = new Application($client->getKernel());
		$application->setAutoExit(false);

		$fp = tmpfile();
		$input = new StringInput($command);
		$output = new StreamOutput($fp);

		$application->run($input, $output);

		fseek($fp, 0);
		$output = '';
		while (!feof($fp)) {
			$output = fread($fp, 4096);
		}
		fclose($fp);

		return $output;
	}


	public function testExecute() {
		$appstate = $this->client->getContainer()->get('appstate');
		$cycle = intval($appstate->getGlobal('cycle', 0));
		$output = $this->runCommand($this->client, "maf:turn -t");

		$this->assertContains('Time for', $output);
		$this->assertEquals($cycle+1, $appstate->getGlobal('cycle'));
	}
}