<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

class RealmRelationType extends AbstractType {

	private $statuses = array(
		'nemesis', 'war', 'peace', 'friend', 'ally'
	);

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'realmrelation_5414',
			'data_class'			=> 'BM2\SiteBundle\Entity\RealmRelation',
			'translation_domain' => 'politics',
			'attr'					=> array('class'=>'wide')
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('target_realm', 'entity', array(
			'placeholder' => 'diplomacy.relations.emptytarget',
			'label'=>'diplomacy.relations.target',
			'class'=>'BM2SiteBundle:Realm',
			'choice_label'=>'name',
			'query_builder'=>function(EntityRepository $er) {
							$qb = $er->createQueryBuilder('r');
							$qb->orderBy('r.name', 'ASC');
							return $qb;
			}
		));

		$choices = array();
		foreach ($this->statuses as $status) {
			$choices[$status] = 'diplomacy.status.'.$status;
		}

		$builder->add('status', 'choice', array(
			'placeholder' => 'diplomacy.relations.emptystatus',
			'label'=>'diplomacy.status.name',
			'required'=>true,
			'choices'=>$choices
		));
		$builder->add('public', 'textarea', array(
			'label'=>'diplomacy.relations.public',
			'trim'=>true,
			'required'=>true
		));
		$builder->add('internal', 'textarea', array(
			'label'=>'diplomacy.relations.internal',
			'trim'=>true,
			'required'=>true
		));
		$builder->add('delivered', 'textarea', array(
			'label'=>'diplomacy.relations.delivered',
			'trim'=>true,
			'required'=>true
		));

		$builder->add('submit', 'submit', array('label'=>'diplomacy.relations.submit'));
	}

	public function getName() {
		return 'realmrelation';
	}
}
