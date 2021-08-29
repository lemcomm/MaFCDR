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
use Calitarus\MessageBundle\Entity\Conversation as OldConv;

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
		$execLimit = 25;

                if ($input->getArgument('all') == 'all') {
                        $output->writeln("Beginning conversation update...");
                } else {
                        $output->writeln("Incorrect confirmation string. String must be 'all'.");
                        return false;
                }
                $stopwatch->start('updateConversations');

                $counter = 0;
                $msgCount = 0;
		$permCount = 0;
		$skipped = 0;
		#$query = $em->createQuery('SELECT c FROM MsgBundle:Conversation c ORDER BY c.id DESC')->setMaxResults(100);

		while ($em->createQuery('SELECT count(c.id) FROM MsgBundle:Conversation c')->getSingleScalarResult() != 0) {
			$output->writeln("Beginning execution loop...");
			$em->clear();
			$executions = 0;
			$microCounter = 0;
			$microMsgCount = 0;
			$microPermCount = 0;
			$query = $em->createQuery('SELECT c FROM MsgBundle:Conversation c ORDER BY c.id DESC');
			$result = $query->iterate();
			while (($row = $result->next()) !== false AND $executions < $execLimit) {
	                        # Prepare loop.
				$oldConv = $row[0];
	                        $participants = new ArrayCollection();
	                        $foundStart = false;
	                        $foundOwner = false;
				$oldest = null;
				$start = null;
				$counter++;
				$microCounter++;
				$executions++;

				$output->writeln("Converting old conversation (ID: ".$oldConv->getId()."): '".$oldConv->getTopic()."'. Counter at ".$counter."/".$msgCount."/".$permCount." (C/M/P).");
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
		                        if ($realm = $oldConv->getAppReference()) {
		                                $newConv->setRealm($realm);
		                        }

		                        # Flag this as a legacy conversation.
		                        $newConv->setType('legacy');

		                        # Start sorting through messages...
					$output->writeln("Converting messages...");
		                        foreach ($oldConv->getMessages() as $oldMsg) {
						$output->writeln("Found message ".$oldMsg->getId().", converting...");
		                                $newMsg = new Message();
		                                $em->persist($newMsg);
						$newMsg->setConversation($newConv);

		                                # Check if we have conversation start date yet and set if don't.
		                                if (!$foundStart) {
		                                        # Due to how the old message system calls messages, the first message returned by getMessages() is the first in that convo.
							$start = $oldMsg->getTs();
							$newConv->setCreated($start);
		                                        $foundStart = true;
		                                }
						if (!$oldest || $oldest < $oldMsg->getTs()) {
							$oldest = $oldMsg->getTs();
						}

		                                # Carryover old msg send date.
		                                $newMsg->setSent($oldMsg->getTs());
						$newMsg->setCycle($oldMsg->getCycle());

						# System messages will not have a participant, and thus we skip this.
		                                if ($oldMsg->getSender()) {
							$char = $oldMsg->getSender()->getAppUser();
		                                        $newMsg->setSender($char);
			                                # Check if we've already seen this participant and build permissions if not.
							if (!$realm) {
			                                        if (!$participants->contains($char)) {
									$output->writeln("... creating private permission for ".$char->getName()." (".$char->getId().")...");
			                                                $participants->add($char);
			                                                $perm = new ConversationPermission();
			                                                $perm->setCharacter($char);
			                                                $perm->setConversation($newConv);
									$perm->setActive(true);
			                                                $em->persist($perm);

			                                                $perm->setStartTime($start);
			                                                if (!$foundOwner && !$oldConv->getSystem()) {
			                                                        # We don't have an owner yet, and this isn't a system-managed or realmconversation, so we need one.
			                                                        $perm->setOwner(true);
			                                                        $foundOwner = true;
			                                                } else {
										$perm->setOwner(false);
									}
									$perm->setManager(false);
			                                                $perm->setUnread(0);
									$permCount++;
									$microPermCount++;
			                                        }
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
					if ($oldest) {
						$newConv->setUpdated($oldest);
					}
					if ($realm) {
						$output->writeln("Creating new realm conversation permissions...");
						foreach ($realm->findMembers() as $char) {
							$output->writeln("... creating permission for ".$char->getName()." (".$char->getId().")...");
							$perm = new ConversationPermission();
							$em->persist($perm);
							$perm->setCharacter($char);
							$perm->setConversation($newConv);
							$perm->setStartTime($start);
							$perm->setActive(true);
							$perm->setOwner(false);
							$perm->setManager(false);
							$perm->setUnread(0);
							$permCount++;
							$microPermCount++;
						}
					}
		                        $em->remove($oldConv); #Cascade delete via entity removes all children objects. :)
				} else {
					$output->writeln("Skipping empty conversation...");
					$em->remove($oldConv);
					$skipped++;
				}
	                }
			$output->writeln("Inserting ".$microCounter." conversations, ".$microMsgCount." messages and ".$microPermCount." permissions to database...");
			$em->flush();
			if ($executions == $execLimit) {
				$output->writeln('Execution limit reached. Resetting doctrine...');
			}

		}
		#$output->writeln('Cleaning up messages left over from the old tower link message system Tom tried out.');
		#$query = $em->createQuery('DELETE FROM BM2SiteBundle:Message m WHERE m.conversation IS NULL');
		#$query->execute();
		$event = $stopwatch->stop('updateConversations');
		$output->writeln('End of update reached. '.$counter.' conversations, incuding '.$msgCount.' messages and '.$permCount.' permissions, updated in '.($event->getDuration()/1000).' seconds. '.$skipped.' conversations were skipped this run.');
	}
}
