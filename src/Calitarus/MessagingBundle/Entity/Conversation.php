<?php 

namespace Calitarus\MessagingBundle\Entity;

class Conversation {

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
    private $topic;

    /**
     * @var string
     */
    private $system;

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
    private $messages;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $children;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $metadata;

    /**
     * @var \Calitarus\MessagingBundle\Entity\Conversation
     */
    private $parent;

    /**
     * @var \BM2\SiteBundle\Entity\Realm
     */
    private $app_reference;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->messages = new \Doctrine\Common\Collections\ArrayCollection();
        $this->children = new \Doctrine\Common\Collections\ArrayCollection();
        $this->metadata = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set topic
     *
     * @param string $topic
     * @return Conversation
     */
    public function setTopic($topic)
    {
        $this->topic = $topic;

        return $this;
    }

    /**
     * Get topic
     *
     * @return string 
     */
    public function getTopic()
    {
        return $this->topic;
    }

    /**
     * Set system
     *
     * @param string $system
     * @return Conversation
     */
    public function setSystem($system)
    {
        $this->system = $system;

        return $this;
    }

    /**
     * Get system
     *
     * @return string 
     */
    public function getSystem()
    {
        return $this->system;
    }

    /**
     * Set depth
     *
     * @param integer $depth
     * @return Conversation
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
     * Add messages
     *
     * @param \Calitarus\MessagingBundle\Entity\Message $messages
     * @return Conversation
     */
    public function addMessage(\Calitarus\MessagingBundle\Entity\Message $messages)
    {
        $this->messages[] = $messages;

        return $this;
    }

    /**
     * Remove messages
     *
     * @param \Calitarus\MessagingBundle\Entity\Message $messages
     */
    public function removeMessage(\Calitarus\MessagingBundle\Entity\Message $messages)
    {
        $this->messages->removeElement($messages);
    }

    /**
     * Get messages
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * Add children
     *
     * @param \Calitarus\MessagingBundle\Entity\Conversation $children
     * @return Conversation
     */
    public function addChild(\Calitarus\MessagingBundle\Entity\Conversation $children)
    {
        $this->children[] = $children;

        return $this;
    }

    /**
     * Remove children
     *
     * @param \Calitarus\MessagingBundle\Entity\Conversation $children
     */
    public function removeChild(\Calitarus\MessagingBundle\Entity\Conversation $children)
    {
        $this->children->removeElement($children);
    }

    /**
     * Get children
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Add metadata
     *
     * @param \Calitarus\MessagingBundle\Entity\ConversationMetadata $metadata
     * @return Conversation
     */
    public function addMetadatum(\Calitarus\MessagingBundle\Entity\ConversationMetadata $metadata)
    {
        $this->metadata[] = $metadata;

        return $this;
    }

    /**
     * Remove metadata
     *
     * @param \Calitarus\MessagingBundle\Entity\ConversationMetadata $metadata
     */
    public function removeMetadatum(\Calitarus\MessagingBundle\Entity\ConversationMetadata $metadata)
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
     * Set parent
     *
     * @param \Calitarus\MessagingBundle\Entity\Conversation $parent
     * @return Conversation
     */
    public function setParent(\Calitarus\MessagingBundle\Entity\Conversation $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent
     *
     * @return \Calitarus\MessagingBundle\Entity\Conversation 
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Set app_reference
     *
     * @param \BM2\SiteBundle\Entity\Realm $appReference
     * @return Conversation
     */
    public function setAppReference(\BM2\SiteBundle\Entity\Realm $appReference = null)
    {
        $this->app_reference = $appReference;

        return $this;
    }

    /**
     * Get app_reference
     *
     * @return \BM2\SiteBundle\Entity\Realm 
     */
    public function getAppReference()
    {
        return $this->app_reference;
    }
}
