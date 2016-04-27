<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class RealmOfficialsType extends AbstractType {

	private $candidates;
	private $holders;

	public function __construct($candidates, $holders) {
		$this->candidates = array();
		foreach ($candidates as $candidate) {
			$this->candidates[] = $candidate->getId();
		}
		$this->holders = $holders;
	}


	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'realmofficials_96532',
			'translation_domain' => 'politics',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$candidates = $this->candidates;

		$builder->add('candidates', 'entity', array(
			'label'=>'position.appoint.candidates',
			'required' => false,
			'multiple' => true,
			'expanded' => true,
			'data' => $this->holders,
			'class'=>'BM2SiteBundle:Character',
			'choice_label'=>'name',
			'query_builder'=>function(EntityRepository $er) use ($candidates) {
				return $er->createQueryBuilder('c')->where('c in (:all)')->setParameter('all', $candidates)->orderBy('c.name', 'ASC');
			}
		));

		$builder->add('submit', 'submit', array('label'=>'position.appoint.submit'));
	}

	public function getName() {
		return 'realmofficials';
	}
}
