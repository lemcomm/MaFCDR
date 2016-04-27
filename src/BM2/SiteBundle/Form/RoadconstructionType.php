<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class RoadconstructionType extends AbstractType {

	private $settlement;
	private $roadsdata;

	public function __construct($settlement, $roadsdata) {
		$this->settlement = $settlement;
		$this->roadsdata = $roadsdata;
	}

	public function getName() {
		return 'roadconstruction';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'roadconstruction_0345',
			'translation_domain' => 'actions'
		));
	}

// FIXME: why don't I go on the entity here and access the workers variable directly ??

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('existing', 'form');

		foreach ($this->roadsdata as $data) {
			// max road level: 5
			if ($data['road']->getQuality()>=5) {
				$disabled = true;
			} else {
				$disabled = false;
			}
			$builder->get('existing')->add(
				(string)$data['road']->getId(),
				'percent',
				array(
					'required' => false,
					'disabled' => $disabled,
					'precision' => 2,
					'data' => $data['road']->getWorkers(),
					'attr' => array('size'=>3, 'class' => 'assignment')
				)
			);
		}

		$builder->add('new', 'form');

		$builder->get('new')->add('workers', 'percent',
			array(
				'required' => false,
				'precision' => 2,
				'attr' => array('size'=>3, 'class' => 'assignment')
			)
		);
		$geo = $this->settlement->getGeoData();
		// yes, these are different. the first ensures that at least one of the features belongs to your region
		$builder->get('new')->add('from', 'entity', array(
			'label'=>'economy.roads.from',
			'required'=>false,
			'placeholder'=>'form.choose',
			'choice_translation_domain' => true,
			'class'=>'BM2SiteBundle:GeoFeature', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($geo) {
				return $er->createQueryBuilder('f')->where('f.geo_data = :geo')->orderBy('f.name')->setParameters(array('geo'=>$geo));
			}
		));
		// 100m or so beyond the border, to include border posts, etc. - hardcoded value for now
		$builder->get('new')->add('to', 'entity', array(
			'label'=>'economy.roads.to',
			'required'=>false,
			'placeholder'=>'form.choose',
			'choice_translation_domain' => true,
			'class'=>'BM2SiteBundle:GeoFeature', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($geo) {
				return $er->createQueryBuilder('f')->from('BM2SiteBundle:GeoData', 'g')
					->where('ST_Distance(g.poly, f.location) < :gutter')
					->andWhere('g = :geo')->orderBy('f.name')->setParameters(array('geo'=>$geo, 'gutter'=>100));
			}
		));

	}


}
