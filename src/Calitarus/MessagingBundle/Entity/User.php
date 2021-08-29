<?php 

namespace Calitarus\MessagingBundle\Entity;


class User {

	public function getName() {
		return $this->app_user->getName();
	}


	public function countNewMessages() {
		$new = 0;
		foreach ($this->getConversationsMetadata() as $meta) {
			$new += $meta->getUnread();
		}
		return $new;
	}


	public function hasNewMessages() {
		foreach ($this->getConversationsMetadata() as $meta) {
			if ($meta->getUnread() > 0 ) {
				return true;
			}
		}
		return false;
	}

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \BM2\SiteBundle\Entity\Character
     */
    private $app_user;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $sent_messages;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $owned_groups;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $conversations_metadata;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $messages_metadata;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $groups;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->sent_messages = new \Doctrine\Common\Collections\ArrayCollection();
        $this->owned_groups = new \Doctrine\Common\Collections\ArrayCollection();
        $this->conversations_metadata = new \Doctrine\Common\Collections\ArrayCollection();
        $this->messages_metadata = new \Doctrine\Common\Collections\ArrayCollection();
        $this->groups = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set app_user
     *
     * @param \BM2\SiteBundle\Entity\Character $appUser
     * @return User
     */
    public function setAppUser(\BM2\SiteBundle\Entity\Character $appUser = null)
    {
        $this->app_user = $appUser;

        return $this;
    }

    /**
     * Get app_user
     *
     * @return \BM2\SiteBundle\Entity\Character 
     */
    public function getAppUser()
    {
        return $this->app_user;
    }

    /**
     * Add sent_messages
     *
     * @param \Calitarus\MessagingBundle\Entity\Message $sentMessages
     * @return User
     */
    public function addSentMessage(\Calitarus\MessagingBundle\Entity\Message $sentMessages)
    {
        $this->sent_messages[] = $sentMessages;

        return $this;
    }

    /**
     * Remove sent_messages
     *
     * @param \Calitarus\MessagingBundle\Entity\Message $sentMessages
     */
    public function removeSentMessage(\Calitarus\MessagingBundle\Entity\Message $sentMessages)
    {
        $this->sent_messages->removeElement($sentMessages);
    }

    /**
     * Get sent_messages
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getSentMessages()
    {
        return $this->sent_messages;
    }

    /**
     * Add owned_groups
     *
     * @param \Calitarus\MessagingBundle\Entity\Group $ownedGroups
     * @return User
     */
    public function addOwnedGroup(\Calitarus\MessagingBundle\Entity\Group $ownedGroups)
    {
        $this->owned_groups[] = $ownedGroups;

        return $this;
    }

    /**
     * Remove owned_groups
     *
     * @param \Calitarus\MessagingBundle\Entity\Group $ownedGroups
     */
    public function removeOwnedGroup(\Calitarus\MessagingBundle\Entity\Group $ownedGroups)
    {
        $this->owned_groups->removeElement($ownedGroups);
    }

    /**
     * Get owned_groups
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getOwnedGroups()
    {
        return $this->owned_groups;
    }

    /**
     * Add conversations_metadata
     *
     * @param \Calitarus\MessagingBundle\Entity\ConversationMetadata $conversationsMetadata
     * @return User
     */
    public function addConversationsMetadatum(\Calitarus\MessagingBundle\Entity\ConversationMetadata $conversationsMetadata)
    {
        $this->conversations_metadata[] = $conversationsMetadata;

        return $this;
    }

    /**
     * Remove conversations_metadata
     *
     * @param \Calitarus\MessagingBundle\Entity\ConversationMetadata $conversationsMetadata
     */
    public function removeConversationsMetadatum(\Calitarus\MessagingBundle\Entity\ConversationMetadata $conversationsMetadata)
    {
        $this->conversations_metadata->removeElement($conversationsMetadata);
    }

    /**
     * Get conversations_metadata
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getConversationsMetadata()
    {
        return $this->conversations_metadata;
    }

    /**
     * Add messages_metadata
     *
     * @param \Calitarus\MessagingBundle\Entity\MessageMetadata $messagesMetadata
     * @return User
     */
    public function addMessagesMetadatum(\Calitarus\MessagingBundle\Entity\MessageMetadata $messagesMetadata)
    {
        $this->messages_metadata[] = $messagesMetadata;

        return $this;
    }

    /**
     * Remove messages_metadata
     *
     * @param \Calitarus\MessagingBundle\Entity\MessageMetadata $messagesMetadata
     */
    public function removeMessagesMetadatum(\Calitarus\MessagingBundle\Entity\MessageMetadata $messagesMetadata)
    {
        $this->messages_metadata->removeElement($messagesMetadata);
    }

    /**
     * Get messages_metadata
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getMessagesMetadata()
    {
        return $this->messages_metadata;
    }

    /**
     * Add groups
     *
     * @param \Calitarus\MessagingBundle\Entity\Group $groups
     * @return User
     */
    public function addGroup(\Calitarus\MessagingBundle\Entity\Group $groups)
    {
        $this->groups[] = $groups;

        return $this;
    }

    /**
     * Remove groups
     *
     * @param \Calitarus\MessagingBundle\Entity\Group $groups
     */
    public function removeGroup(\Calitarus\MessagingBundle\Entity\Group $groups)
    {
        $this->groups->removeElement($groups);
    }

    /**
     * Get groups
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getGroups()
    {
        return $this->groups;
    }
}
