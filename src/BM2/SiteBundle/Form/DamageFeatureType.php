<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class DamageFeatureType extends AbstractType {

	private $features;

	public function __construct($features) {
		$this->features = $features;
	}

	public function getName() {
		return 'damagefeatures';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'damagefeatures_9615',
			'translation_domain' => 'actions'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$features = $this->features;
		$builder->add('target', 'entity', array(
			'label'=>'military.damage.target',
			'expanded'=>true,
			'class'=>'BM2SiteBundle:GeoFeature', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($features) {
				return $er->createQueryBuilder('f')->where('f IN (:features)')->orderBy('f.name')->setParameters(array('features'=>$features));
		}));

		$builder->add('submit', 'submit', array('label'=>'military.damage.submit'));
	}


}
