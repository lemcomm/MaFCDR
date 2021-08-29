<?php 

namespace Calitarus\MessagingBundle\Entity;

class Message {

	public function getRelatedMessagesExcept(Message $hide) {
		return $this->getRelatedMessages()->filter(
			function($entry) use ($hide) {
				return ($entry->getTarget() != $hide);
			}
		);
	}

	public function getRelatedToMeExcept(Message $hide) {
		return $this->getRelatedToMe()->filter(
			function($entry) use ($hide) {
				return ($entry->getSource() != $hide);
			}
		);
	}

	public function findMeta(User $user) {
		return $this->getMetadata()->filter(
			function($entry) use ($user) {
				return ($entry->getUser() == $user);
			}
		)->first();
	}

    /**
     * @var string
     */
    private $content;

    /**
     * @var \DateTime
     */
    private $ts;

    /**
     * @var integer
     */
    private $cycle;

    /**
     * @var integer
     */
    private $depth;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $metadata;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $related_messages;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $related_to_me;

    /**
     * @var \Calitarus\MessagingBundle\Entity\User
     */
    private $sender;

    /**
     * @var \Calitarus\MessagingBundle\Entity\Conversation
     */
    private $conversation;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->metadata = new \Doctrine\Common\Collections\ArrayCollection();
        $this->related_messages = new \Doctrine\Common\Collections\ArrayCollection();
        $this->related_to_me = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set content
     *
     * @param string $content
     * @return Message
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get content
     *
     * @return string 
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Set ts
     *
     * @param \DateTime $ts
     * @return Message
     */
    public function setTs($ts)
    {
        $this->ts = $ts;

        return $this;
    }

    /**
     * Get ts
     *
     * @return \DateTime 
     */
    public function getTs()
    {
        return $this->ts;
    }

    /**
     * Set cycle
     *
     * @param integer $cycle
     * @return Message
     */
    public function setCycle($cycle)
    {
        $this->cycle = $cycle;

        return $this;
    }

    /**
     * Get cycle
     *
     * @return integer 
     */
    public function getCycle()
    {
        return $this->cycle;
    }

    /**
     * Set depth
     *
     * @param integer $depth
     * @return Message
     */
    public function setDepth($depth)
    {
        $this->depth = $depth;

        return $this;
    }

    /**
     * Get depth
     *
     * @return integer 
     */
    public function getDepth()
    {
        return $this->depth;
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
     * Add metadata
     *
     * @param \Calitarus\MessagingBundle\Entity\MessageMetadata $metadata
     * @return Message
     */
    public function addMetadatum(\Calitarus\MessagingBundle\Entity\MessageMetadata $metadata)
    {
        $this->metadata[] = $metadata;

        return $this;
    }

    /**
     * Remove metadata
     *
     * @param \Calitarus\MessagingBundle\Entity\MessageMetadata $metadata
     */
    public function removeMetadatum(\Calitarus\MessagingBundle\Entity\MessageMetadata $metadata)
    {
        $this->metadata->removeElement($metadata);
    }

    /**
     * Get metadata
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Add related_messages
     *
     * @param \Calitarus\MessagingBundle\Entity\MessageRelation $relatedMessages
     * @return Message
     */
    public function addRelatedMessage(\Calitarus\MessagingBundle\Entity\MessageRelation $relatedMessages)
    {
        $this->related_messages[] = $relatedMessages;

        return $this;
    }

    /**
     * Remove related_messages
     *
     * @param \Calitarus\MessagingBundle\Entity\MessageRelation $relatedMessages
     */
    public function removeRelatedMessage(\Calitarus\MessagingBundle\Entity\MessageRelation $relatedMessages)
    {
        $this->related_messages->removeElement($relatedMessages);
    }

    /**
     * Get related_messages
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getRelatedMessages()
    {
        return $this->related_messages;
    }

    /**
     * Add related_to_me
     *
     * @param \Calitarus\MessagingBundle\Entity\MessageRelation $relatedToMe
     * @return Message
     */
    public function addRelatedToMe(\Calitarus\MessagingBundle\Entity\MessageRelation $relatedToMe)
    {
        $this->related_to_me[] = $relatedToMe;

        return $this;
    }

    /**
     * Remove related_to_me
     *
     * @param \Calitarus\MessagingBundle\Entity\MessageRelation $relatedToMe
     */
    public function removeRelatedToMe(\Calitarus\MessagingBundle\Entity\MessageRelation $relatedToMe)
    {
        $this->related_to_me->removeElement($relatedToMe);
    }

    /**
     * Get related_to_me
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getRelatedToMe()
    {
        return $this->related_to_me;
    }

    /**
     * Set sender
     *
     * @param \Calitarus\MessagingBundle\Entity\User $sender
     * @return Message
     */
    public function setSender(\Calitarus\MessagingBundle\Entity\User $sender = null)
    {
        $this->sender = $sender;

        return $this;
    }

    /**
     * Get sender
     *
     * @return \Calitarus\MessagingBundle\Entity\User 
     */
    public function getSender()
    {
        return $this->sender;
    }

    /**
     * Set conversation
     *
     * @param \Calitarus\MessagingBundle\Entity\Conversation $conversation
     * @return Message
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
}
