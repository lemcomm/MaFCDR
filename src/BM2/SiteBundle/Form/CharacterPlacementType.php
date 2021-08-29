<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

class CharacterPlacementType extends AbstractType {

	private $character;
	private $type;

	public function __construct($type, $character) {
		$this->type = $type;
		$this->character = $character;
	}

	public function getName() {
		return 'character_placement_'.$this->type;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'character_placement_35712',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		switch ($this->type) {
			case 'offer':		$this->buildForm_Offer($builder); break;
			case 'family':		$this->buildForm_Family($builder); break;
		}

		$builder->add('submit', 'submit', array('label'=>'character.start.submit'));
	}

	private function buildForm_Offer(FormBuilderInterface $builder) {
		$builder->add('offer', 'entity', array(
			'label'=>'character.start.offerchoice',
			'required'=>true,
			'expanded'=>false,
			'placeholder'=>'form.choose',
			'class'=>'BM2SiteBundle:KnightOffer',
			'choice_label'=>'id',
			'query_builder'=>
				function(EntityRepository $er) {
					return $er->createQueryBuilder('o')->join('o.settlement', 's')->where('s.owner IS NOT NULL');
			}
		));
	}

	private function buildForm_Family(FormBuilderInterface $builder) {
		$char = $this->character;
		$builder->add('settlement', 'entity', array(
			'label'=>'character.start.estate',
			'required'=>true,
			'placeholder'=>'form.choose',
			'class'=>'BM2SiteBundle:Settlement', 'choice_label'=>'nameWithOwner', 'query_builder'=>
				function(EntityRepository $er) use ($char) {
					return $er->createQueryBuilder('s')->join('s.owner', 'o')->where('o.user = :user')->setParameter('user', $char->getUser());
		}));
	}


}
