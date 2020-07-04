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

                $counter = 0;
                $msgCount = 0;
                $all = $em->getRepository('MsgBundle:Conversation')->findAll();
                foreach ($all as $oldConv) {
                        $stopwatch->start('updateConversations');
                        # Prepare loop.
                        $participants = new ArrayCollection();
                        $foundStart = false;
                        $foundOwner = false;

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
                                                $participants->add($oldMsg->getSender()->getAppUser());
                                                $perm = new ConversationPermission();
                                                $perm->setCharacter($oldMsg->getSender()->getAppUser());
                                                $perm->setConversation($newConv);
                                                $em->persist($perm);

                                                $perm->setStart($oldMsg->getTs());
                                                if (!$foundOwner && !$oldConv->getSystem()) {
                                                        # We don't have an owner yet, and this isn't a system-managed conversation, so we need one.
                                                        $perm->setOwner($oldMsg->getSender()->getAppUser());
                                                        $foundOwner = true;
                                                }
                                                $perm->setUnread(0);
                                        }
                                }
                                $newMsg->setContent($oldMsg->getContent());
                                $newMsg->setType('legacy');
                                $msgCount++;
                        }

                        $em->remove($oldConv);
                        #$counter++;
                        $count = 1000;
                        if ($counter == 1000) {
                                $event = $stopwatch->stop('updateConversations');
                                break;
                                $output->writeln('Execution limit reached. 1000 conversations, incuding '.$msgCount.' messages updated in '.($event->getDuration()/1000).' seconds.');
                        }
                }
                $em->flush();
	}
}
