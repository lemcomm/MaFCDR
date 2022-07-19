<?php

namespace BM2\SiteBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Skill
 */
class Skill {

        public function evaluate() {
                $pract = $this->practice?$this->practice:1;
                $theory = $this->theory?$this->theory:1;
                if ($pract >= $theory * 3) {
                        # Theory is less than a third of pracitce. Use practice but subtract a quarter.
                        $score = $pract * 0.75;
                } elseif ($pract * 10 <= $theory) {
                        # Practice is less than a tenth of theory. Use theory but remove four fifths.
                        $score = $theory * 0.2;
                } else {
                        $score = max($theory, $pract);
                }
                return sqrt($score * 5);
        }

        public function getScore() {
                $char = $this->character;
                $scores = [$this->evaluate()];
                foreach ($char->getSkills() as $each) {
                        if ($each->getCategory() === $this->category && $each !== $this) {
                                $scores[] = $each->evaluate()/2;
                        }
                }
                return max($scores);
        }
}
