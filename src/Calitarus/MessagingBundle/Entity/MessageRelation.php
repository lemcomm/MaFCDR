<?php

namespace Calitarus\MessagingBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MessageRelation
 */
class MessageRelation
{
    /**
     * @var string
     */
    private $type;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \Calitarus\MessagingBundle\Entity\Message
     */
    private $source;

    /**
     * @var \Calitarus\MessagingBundle\Entity\Message
     */
    private $target;


    /**
     * Set type
     *
     * @param string $type
     * @return MessageRelation
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string 
     */
    public function getType()
    {
        return $this->type;
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
     * Set source
     *
     * @param \Calitarus\MessagingBundle\Entity\Message $source
     * @return MessageRelation
     */
    public function setSource(\Calitarus\MessagingBundle\Entity\Message $source = null)
    {
        $this->source = $source;

        return $this;
    }

    /**
     * Get source
     *
     * @return \Calitarus\MessagingBundle\Entity\Message 
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Set target
     *
     * @param \Calitarus\MessagingBundle\Entity\Message $target
     * @return MessageRelation
     */
    public function setTarget(\Calitarus\MessagingBundle\Entity\Message $target = null)
    {
        $this->target = $target;

        return $this;
    }

    /**
     * Get target
     *
     * @return \Calitarus\MessagingBundle\Entity\Message 
     */
    public function getTarget()
    {
        return $this->target;
    }
}
