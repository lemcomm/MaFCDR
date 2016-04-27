<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class WarType extends AbstractType {

	private $me;

	public function __construct($me) {
		$this->me = $me;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'war_1911',
			'translation_domain' => 'actions',
			'data_class'			=> 'BM2\SiteBundle\Entity\War',
			'attr'					=> array('class'=>'wide')
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('summary', 'text', array(
			'label'=>'military.war.summary',
			'required'=>true,
			'attr' => array('size'=>80, 'maxlength'=>240)
		));
		$builder->add('description', 'textarea', array(
			'label'=>'military.war.desc',
			'required'=>true,
		));
		$me = $this->me;
		$builder->add('targets', 'entity', array(
			'label'=>'military.war.targets',
			'required'=>true,
			'multiple'=>true,
			'mapped'=>false,
			'placeholder'=>'form.choose',
			'class'=>'BM2SiteBundle:Settlement', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($me) {
				return $er->createQueryBuilder('s')->where('s.realm NOT IN (:me)')->orderBy('s.name')->setParameters(array('me'=>$me));
			}
		));

		$builder->add('submit', 'submit', array('label'=>'military.war.declare'));
	}

	public function getName() {
		return 'war';
	}
}
