<?php 

namespace BM2\SiteBundle\Entity;


class RealmPosition {
  
        public function getPosType(){
                if($this->type() == NULL) {
                        return 'other';
                } else return $this->type();
        }
        
}
