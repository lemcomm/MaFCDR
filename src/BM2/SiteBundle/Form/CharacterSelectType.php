<?php

namespace BM2\SiteBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class CharacterSelectType extends AbstractType {

	private $characters;
	private $empty;
	private $label;
	private $submit;
	private $domain;
	private $required;

	public function __construct($characters, $empty="", $label="", $submit="", $domain="politics", $required=true) {
		$this->characters = $characters;
		$this->empty = $empty;
		$this->label = $label;
		$this->submit = $submit;
		$this->domain = $domain;
		$this->required = $required;
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

		$builder->add('target', EntityType::class, array(
			'placeholder' => $this->empty,
			'label' => $this->label,
			'class'=>'BM2SiteBundle:Character',
			'choice_label'=>'name',
			'required'=>$this->required,
			'query_builder'=>function(EntityRepository $er) use ($characters) {
				$qb = $er->createQueryBuilder('c');
				$qb->where('c IN (:characters)');
				$qb->setParameter('characters', $characters);
				return $qb;
			},
		));

		$builder->add('submit', SubmitType::class, array('label'=>$this->submit));
	}


}
