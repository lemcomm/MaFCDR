<?php 

namespace BM2\SiteBundle\Entity;


class RealmPosition {
  
        public function Type(){
                if($this->getType() == NULL) {
                        return 'other'
                } else return $this->getType();
}
