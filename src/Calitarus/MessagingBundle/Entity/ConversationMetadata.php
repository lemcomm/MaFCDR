<?php 

namespace Calitarus\MessagingBundle\Entity;

class ConversationMetadata {

	public function hasRight(Right $right) {
		if ($this->rights->contains($right)) return true;

		return $this->hasRightByName('owner');
	}

	public function hasRightByName($name) {
		return $this->rights->exists(function($key, $element) use ($name) { return $element->getName() == $name || $element->getName() == 'owner'; } );
	}

    /**
     * @var integer
     */
    private $unread;

    /**
     * @var \DateTime
     */
    private $last_read;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \Calitarus\MessagingBundle\Entity\Conversation
     */
    private $conversation;

    /**
     * @var \Calitarus\MessagingBundle\Entity\User
     */
    private $user;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $flags;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $rights;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->flags = new \Doctrine\Common\Collections\ArrayCollection();
        $this->rights = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set unread
     *
     * @param integer $unread
     * @return ConversationMetadata
     */
    public function setUnread($unread)
    {
        $this->unread = $unread;

        return $this;
    }

    /**
     * Get unread
     *
     * @return integer 
     */
    public function getUnread()
    {
        return $this->unread;
    }

    /**
     * Set last_read
     *
     * @param \DateTime $lastRead
     * @return ConversationMetadata
     */
    public function setLastRead($lastRead)
    {
        $this->last_read = $lastRead;

        return $this;
    }

    /**
     * Get last_read
     *
     * @return \DateTime 
     */
    public function getLastRead()
    {
        return $this->last_read;
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
     * Set conversation
     *
     * @param \Calitarus\MessagingBundle\Entity\Conversation $conversation
     * @return ConversationMetadata
     */
    public function setConversation(\Calitarus\MessagingBundle\Entity\Conversation $conversation = null)
    {
        $this->conversation = $conversation;

        return $this;
    }

    /**
     * Get conversation
     *
     * @return \Calitarus\MessagingBundle\Entity\Conversation 
     */
    public function getConversation()
    {
        return $this->conversation;
    }

    /**
     * Set user
     *
     * @param \Calitarus\MessagingBundle\Entity\User $user
     * @return ConversationMetadata
     */
    public function setUser(\Calitarus\MessagingBundle\Entity\User $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \Calitarus\MessagingBundle\Entity\User 
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Add flags
     *
     * @param \Calitarus\MessagingBundle\Entity\Flag $flags
     * @return ConversationMetadata
     */
    public function addFlag(\Calitarus\MessagingBundle\Entity\Flag $flags)
    {
        $this->flags[] = $flags;

        return $this;
    }

    /**
     * Remove flags
     *
     * @param \Calitarus\MessagingBundle\Entity\Flag $flags
     */
    public function removeFlag(\Calitarus\MessagingBundle\Entity\Flag $flags)
    {
        $this->flags->removeElement($flags);
    }

    /**
     * Get flags
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getFlags()
    {
        return $this->flags;
    }

    /**
     * Add rights
     *
     * @param \Calitarus\MessagingBundle\Entity\Right $rights
     * @return ConversationMetadata
     */
    public function addRight(\Calitarus\MessagingBundle\Entity\Right $rights)
    {
        $this->rights[] = $rights;

        return $this;
    }

    /**
     * Remove rights
     *
     * @param \Calitarus\MessagingBundle\Entity\Right $rights
     */
    public function removeRight(\Calitarus\MessagingBundle\Entity\Right $rights)
    {
        $this->rights->removeElement($rights);
    }

    /**
     * Get rights
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getRights()
    {
        return $this->rights;
    }
}
