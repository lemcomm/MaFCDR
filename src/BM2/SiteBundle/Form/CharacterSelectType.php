<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class CharacterSelectType extends AbstractType {

	private $characters;
	private $empty;
	private $label;
	private $submit;
	private $domain;

	public function __construct($characters, $empty="", $label="", $submit="", $domain="politics") {
		$this->characters = $characters;
		$this->empty = $empty;
		$this->label = $label;
		$this->submit = $submit;
		$this->domain = $domain;
	}

	public function getName() {
		return 'character';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'character_7141',
			'translation_domain' => $this->domain
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$characters = $this->characters;

		$builder->add('target', 'entity', array(
			'placeholder' => $this->empty,
			'label' => $this->label,
			'class'=>'BM2SiteBundle:Character', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($characters) {
				$qb = $er->createQueryBuilder('c');
				$qb->where('c IN (:characters)');
				$qb->setParameter('characters', $characters);
				return $qb;
			},
		));

		$builder->add('submit', 'submit', array('label'=>$this->submit));
	}


}
