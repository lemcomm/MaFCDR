<?php

namespace BM2\SiteBundle\Form;

use BM2\SiteBund\Entity\Settlement;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

class UnitRebaseType extends AbstractType {

	public function __construct($options) {
		$this->options = $options;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'rebase_12345',
			'translation_domain' => 'actions',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$options = $this->options;

		$builder->add('settlement', EntityType::class, array(
			'label' => 'unit.rebase.settlement',
			'multiple'=>false,
			'expanded'=>false,
			'class'=>'BM2SiteBundle:Settlement',
                        'choice_label'=>'name',
                        'query_builder'=>function(EntityRepository $er) use ($options) {
				$qb = $er->createQueryBuilder('s');
				$qb->where('s.id in (:options)')->setParameter('options', $options);
				$qb->orderBy('s.name');
				return $qb;
			},
                        'placeholder' => 'unit.rebase.none'
		));

		$builder->add('submit', SubmitType::class, array(
                        'label'=>'button.submit',
                        'translation_domain'=>'settings',));
	}
}
