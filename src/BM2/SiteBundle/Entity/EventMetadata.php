<?php 

namespace BM2\SiteBundle\Entity;

class EventMetadata {

	private $access_from;
	private $access_until;
	private $last_access;
	private $id;
	private $log;
	private $reader;

	public function countNewEvents() {
		$count = 0;
		if ($this->getAccessUntil()) return 0; // FIXME: this is a hack to prevent the new start lighting up for closed logs
		foreach ($this->getLog()->getEvents() as $event) {
			if ($event->getTs() > $this->last_access) {
				$count++;
			}
		}
		return $count;
	}

	public function hasNewEvents() {
		if ($this->getAccessUntil()) return false; // FIXME: this is a hack to prevent the new start lighting up for closed logs
		foreach ($this->getLog()->getEvents() as $event) {
			if ($event->getTs() > $this->last_access) {
				return true;
			}
		}
		return false;		
	}

    /**
     * Set access_from
     *
     * @param integer $accessFrom
     * @return EventMetadata
     */
    public function setAccessFrom($accessFrom = null)
    {
        $this->access_from = $accessFrom;

        return $this;
    }

    /**
     * Get access_from
     *
     * @return integer 
     */
    public function getAccessFrom()
    {
        return $this->access_from;
    }

    /**
     * Set access_until
     *
     * @param integer $accessUntil
     * @return EventMetadata
     */
    public function setAccessUntil($accessUntil = null)
    {
        $this->access_until = $accessUntil;

        return $this;
    }

    /**
     * Get access_until
     *
     * @return integer 
     */
    public function getAccessUntil()
    {
        return $this->access_until;
    }

    /**
     * Set last_access
     *
     * @param \DateTime $lastAccess
     * @return EventMetadata
     */
    public function setLastAccess($lastAccess = null)
    {
        $this->last_access = $lastAccess;

        return $this;
    }

    /**
     * Get last_access
     *
     * @return \DateTime 
     */
    public function getLastAccess()
    {
        return $this->last_access;
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
     * Set log
     *
     * @param \BM2\SiteBundle\Entity\EventLog $log
     * @return EventMetadata
     */
    public function setLog(\BM2\SiteBundle\Entity\EventLog $log = null)
    {
        $this->log = $log;

        return $this;
    }

    /**
     * Get log
     *
     * @return \BM2\SiteBundle\Entity\EventLog 
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     * Set reader
     *
     * @param \BM2\SiteBundle\Entity\Character $reader
     * @return EventMetadata
     */
    public function setReader(\BM2\SiteBundle\Entity\Character $reader = null)
    {
        $this->reader = $reader;

        return $this;
    }

    /**
     * Get reader
     *
     * @return \BM2\SiteBundle\Entity\Character 
     */
    public function getReader()
    {
        return $this->reader;
    }
}
