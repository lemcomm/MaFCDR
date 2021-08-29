<?php 

namespace Calitarus\MessagingBundle\Entity;

class MessageMetadata {

	public function hasFlag(Flag $right) {
		return ($this->flags->contains($right));
	}

	public function hasFlagByName($name) {
		return $this->flags->exists(function($key, $element) use ($name) { return $element->getName() == $name; } );
	}

    /**
     * @var integer
     */
    private $score;

    /**
     * @var array
     */
    private $tags;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \Calitarus\MessagingBundle\Entity\Message
     */
    private $message;

    /**
     * @var \Calitarus\MessagingBundle\Entity\User
     */
    private $user;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    private $flags;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->flags = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * Set score
     *
     * @param integer $score
     * @return MessageMetadata
     */
    public function setScore($score)
    {
        $this->score = $score;

        return $this;
    }

    /**
     * Get score
     *
     * @return integer 
     */
    public function getScore()
    {
        return $this->score;
    }

    /**
     * Set tags
     *
     * @param array $tags
     * @return MessageMetadata
     */
    public function setTags($tags)
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * Get tags
     *
     * @return array 
     */
    public function getTags()
    {
        return $this->tags;
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
     * Set message
     *
     * @param \Calitarus\MessagingBundle\Entity\Message $message
     * @return MessageMetadata
     */
    public function setMessage(\Calitarus\MessagingBundle\Entity\Message $message = null)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Get message
     *
     * @return \Calitarus\MessagingBundle\Entity\Message 
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Set user
     *
     * @param \Calitarus\MessagingBundle\Entity\User $user
     * @return MessageMetadata
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
     * @return MessageMetadata
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
}
