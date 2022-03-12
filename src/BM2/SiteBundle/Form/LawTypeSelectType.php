<?php

namespace BM2\SiteBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class LawTypeSelectType extends AbstractType {

	private $types;

	public function __construct($types, $type, $me = null) {
		$this->types = $types;
	}

	public function getName() {
		return 'lawtype';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'law_313375',
			'translation_domain' => $this->domain
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {

		$builder->add('target', EntityType::class, array(
			'placeholder' => 'laws.form.type.empty',
			'label' => 'laws.form.type.label',
			'required'=>true,
			'attr' => array('title'=>'laws.help.types'),
			'class'=>'BM2SiteBundle:LawType',
			'choice_label'=>'name',
			'choices'=>$this->types
		));

		$builder->add('submit', SubmitType::class, array('label'=>$this->submit));
	}


}
