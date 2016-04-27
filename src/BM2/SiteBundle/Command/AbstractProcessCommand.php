<?php

namespace BM2\SiteBundle\Command;

use BM2\SiteBundle\Entity\RegionFamiliarity;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Process\ProcessBuilder;


class AbstractProcessCommand extends ContainerAwareCommand {

	protected $parallel = 4;
	protected $em;
	protected $output;
	protected $stopwatch;
	protected $opt_time;

	protected function configure() {
		$this
			->setName('maf:process:abstract')
			->setDescription('abstract process command - do not call directly')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		throw new \Exception("do not call this command directly");
	}

	protected function start($topic) {
		$this->em = $this->getContainer()->get('doctrine')->getManager();

		$this->output->writeln($topic.": starting...");
		$this->stopwatch = new Stopwatch();
		$this->stopwatch->start($topic);
	}

	protected function process($worker, $entity, $timeout=60) {
		$min = $this->em->createQuery('SELECT MIN(e.id) FROM BM2SiteBundle:'.$entity.' e')->getSingleScalarResult();
		$max = $this->em->createQuery('SELECT MAX(e.id) FROM BM2SiteBundle:'.$entity.' e')->getSingleScalarResult();

		$batch_size = ceil((($max-$min)+1)/$this->parallel);
		$pool = array();
		for ($i=$min; $i<=$max; $i+=$batch_size) {
			$builder = new ProcessBuilder();
			$builder->setPrefix($this->getApplication()->getKernel()->getRootDir().'/console');
			$builder->setArguments(array(
				'--env='.$this->getApplication()->getKernel()->getEnvironment(),
				'maf:worker:'.$worker,
				$i, $i+$batch_size-1
				));
			$builder->setTimeout($timeout);

			$process = $builder->getProcess();
			$process->start();
			$pool[] = $process;
		}
		$this->output->writeln($worker.": started ".count($pool)." jobs");
		$running = 99;
		while ($running > 0) {
			$running = 0;
			foreach ($pool as $p) {
				if ($p->isRunning()) {
					$running++;
				}
			}
			usleep(250);
		}

		foreach ($pool as $p) {
			if (!$p->isSuccessful()) {
				$this->output->writeln('fail: '.$p->getExitCode().' / '.$p->getCommandLine());
				$this->output->writeln($p->getOutput());
			}
		}

	}

	protected function finish($topic) {
		$this->output->writeln($topic.': ...flushing...');
		$this->em->flush();
		if ($this->opt_time) {
			$event = $this->stopwatch->stop($topic);
			$this->output->writeln($topic.": timing ".date("g:i:s").", ".($event->getDuration()/1000)." s, ".(round($event->getMemory()/1024)/1024)." MB");
		}
		$this->output->writeln($topic.": ...complete");
	}

}
