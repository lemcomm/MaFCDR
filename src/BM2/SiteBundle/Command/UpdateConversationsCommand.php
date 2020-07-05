<?php

namespace BM2\SiteBundle\Command;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

use BM2\SiteBundle\Entity\Conversation;
use BM2\SiteBundle\Entity\Message;
use BM2\SiteBundle\Entity\ConversationPermission;

class UpdateConversationsCommand extends ContainerAwareCommand {

	private $inactivityDays = 21;

	protected function configure() {
		$this
			->setName('maf:update:conversations')
			->setDescription('Convert legacy Caltiarus convos to MaF2.0 Convos')
			->addArgument('all', InputArgument::REQUIRED, 'In order to confirm update please add option "all"')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$em = $this->getContainer()->get('doctrine')->getManager();
                $stopwatch = new Stopwatch();

                if ($input->getArgument('all') == 'all') {
                        $output->writeln("Beginning conversation update...");
                } else {
                        $output->writeln("Incorrect confirmation string. String must be 'all'.");
                        return false;
                }
                $stopwatch->start('updateConversations');

                $counter = 0;
                $msgCount = 0;
		$skipped = 0;
		#$query = $em->createQuery('SELECT c FROM MsgBundle:Conversation c ORDER BY c.id DESC')->setMaxResults(100);

		while ($em->createQuery('SELECT count(c.id) FROM MsgBundle:Conversation c')->getSingleScalarResult() != 0) {
			$output->writeln("Beginning execution loop...");
			$em->clear();
			$executions = 0;
			$microCounter = 0;
			$microMsgCount = 0;
			$query = $em->createQuery('SELECT c FROM MsgBundle:Conversation c ORDER BY c.id DESC');
			$result = $query->iterate();
			while ((($row = $result->next()) !== false AND $executions < 100) OR !$row) {
	                        # Prepare loop.
				$oldConv = $row[0];
	                        $participants = new ArrayCollection();
	                        $foundStart = false;
	                        $foundOwner = false;
				$counter++;
				$microCounter++;
				$executions++;

				$output->writeln("Converting old conversation (ID: ".$oldConv->getId().") :".$oldConv->getTopic().". Counter at ".$counter."/".$msgCount." (C/M).");
				if ($oldConv->getMessages()->count() != 0) {
		                        # Create new conversation.
		                        $newConv = new Conversation();
		                        $em->persist($newConv);

		                        # Carryover topic.
		                        $newConv->setTopic($oldConv->getTopic());

		                        # Check for system flag and carry over.
		                        if ($oldConv->getSystem()) {
		                                $newConv->setSystem($oldConv->getSystem());
		                        }

		                        # Check for Realm association and carry over.
		                        if ($oldConv->getAppReference()) {
		                                $newConv->setRealm($oldConv->getAppReference());
		                        }

		                        # Flag this as a legacy conversation.
		                        $newConv->setType('legacy');

		                        # Start sorting through messages...
		                        foreach ($oldConv->getMessages() as $oldMsg) {
						$output->writeln("Found new message, converting...");
		                                $newMsg = new Message();
		                                $em->persist($newMsg);

		                                # Check if we have conversation start date yet and set if don't.
		                                if (!$foundStart) {
		                                        # Due to how the old message system calls messages, the first message returned by getMessages() is the first in that convo.
		                                        $newConv->setCreated($oldMsg->getTs());
		                                        $foundStart = true;
		                                }

		                                # Carryover old msg send date.
		                                $newMsg->setSent($oldMsg->getTs());

		                                # Check if we've already seen this participant and build permissions if not. System messages will not have a participant, and thus we skip this.
		                                if ($oldMsg->getSender()) {
		                                        $newMsg->setSender($oldMsg->getSender()->getAppUser());
		                                        if (!$participants->contains($oldMsg->getSender()->getAppUser())) {
								$output->writeln("Creating new permission...");
		                                                $participants->add($oldMsg->getSender()->getAppUser());
		                                                $perm = new ConversationPermission();
		                                                $perm->setCharacter($oldMsg->getSender()->getAppUser());
		                                                $perm->setConversation($newConv);
		                                                $em->persist($perm);

		                                                $perm->setStartTime($oldMsg->getTs());
		                                                if (!$foundOwner && !$oldConv->getSystem()) {
		                                                        # We don't have an owner yet, and this isn't a system-managed conversation, so we need one.
		                                                        $perm->setOwner(true);
		                                                        $foundOwner = true;
		                                                } else {
									$perm->setOwner(false);
								}
								$perm->setManager(false);
		                                                $perm->setUnread(0);
		                                        }
		                                }
		                                $newMsg->setContent($oldMsg->getContent());
		                                $newMsg->setType('legacy');
		                                $msgCount++;
						$microMsgCount++;
		                        }
					if (!$foundStart) {
						$newConv->setCreated(new \DateTime("now")); #Sigh.
					}
		                        $em->remove($oldConv); #Cascade delete via entity removes all children objects. :)
				} else {
					$output->writeln("Skipping empty conversation...");
					$em->remove($oldConv);
					$skipped++;
				}
		                $em->flush();
	                }
			if ($executions == 100) {
				$output->writeln('End of execution loop reached. '.$microCounter.' conversations, incuding '.$microMsgCount.' messages.');
			}

		}
		$event = $stopwatch->stop('updateConversations');
		$output->writeln('End of update reached. '.$counter.' conversations, incuding '.$msgCount.' messages updated in '.($event->getDuration()/1000).' seconds. '.$skipped.' conversations were skipped this run.');
	}
}
