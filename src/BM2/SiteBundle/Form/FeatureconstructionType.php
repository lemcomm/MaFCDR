<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class FeatureconstructionType extends AbstractType {

	private $features;
	private $river;
	private $coast;

	public function __construct($features, $river, $coast) {
		$this->features = $features;
		$this->river = $river;
		$this->coast = $coast;
	}

	public function getName() {
		return 'featureconstruction';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'     		=> 'featureconstruction_5215',
			'translation_domain' => 'economy'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('existing', 'form');

		foreach ($this->features as $feature) {
			if ($feature->getType()->getHidden()==false) {
				if ($feature->getActive()) {
					$builder->get('existing')->add((string)$feature->getId(), 'text', array(
						'label'=>'feature.name',
						'data'=>$feature->getName(),
						'required'=>true,
						'empty_data'=>'(unnamed)',
						'attr' => array('size'=>20, 'maxlength'=>60)
					));
				} else {
					$builder->get('existing')->add(
						(string)$feature->getId(),
						'percent',
						array(
							'required' => false,
							'precision' => 2,
							'data' => $feature->getWorkers(),
							'attr' => array('size'=>3, 'class' => 'assignment')
						)
					);
				}				
			}
		}
	
		$builder->add('new', 'form');

		$builder->get('new')->add('workers', 'percent',
			array(
				'required' => false,
				'precision' => 2,
				'attr' => array('size'=>3, 'class' => 'assignment')
			)
		);
		$river = $this->river;
		$coast = $this->coast;
		$builder->get('new')->add('type', 'entity', array(
			'required'=>false,
			'placeholder'=>'feature.none',
			'class'=>'BM2SiteBundle:FeatureType',
			'choice_label'=>'nametrans',
			'choice_translation_domain' => true,
			'query_builder'=>function(EntityRepository $er) use ($river, $coast) {
							$qb = $er->createQueryBuilder('t');
							$qb->where('t.hidden=false');
							if (!$river) {
								$qb->andWhere('t.name != :bridge')->setParameter('bridge', 'bridge');
							}
							// FIXME: what about large lakes?
							if (!$coast) {
								$qb->andWhere('t.name != :docks')->setParameter('docks', 'docks');
							}
							return $qb;
			}
		));
		$builder->get('new')->add('name', 'text', array(
			'label'=>'feature.name',
			'required'=>false,
			'empty_data'=>'(unnamed)',
			'attr' => array('size'=>20, 'maxlength'=>60)
		));

		$builder->get('new')->add('location_x', 'hidden', array('required'=>false));
		$builder->get('new')->add('location_y', 'hidden', array('required'=>false));
	}


}
