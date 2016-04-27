<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

use BM2\SiteBundle\Entity\Listing;


class ListMemberType extends AbstractType {

	private $em;
	private $listing;

	public function __construct(EntityManager $em, Listing $listing=null) {
		$this->em = $em;
		$this->listing = $listing;
	}


	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'listmember_1256513',
			'translation_domain' => 'politics',
			'data_class'		=> 'BM2\SiteBundle\Entity\ListMember',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$l = $this->listing;
		if ($l) {
			if ($l->getId()>0) {
				$builder->add('listing', 'entity', array(
					'required' => true,
					'class'=>'BM2SiteBundle:Listing', 'choice_label'=>'id', 'query_builder'=>function(EntityRepository $er) use ($l) {
						return $er->createQueryBuilder('l')->where('l = :l')->setParameter('l',$l);
					}
				));
			}
			$builder->add('priority', 'integer', array(
				'required' => true,
			));
			$builder->add('allowed', 'checkbox', array(
				'required' => false,
			));
		}
		$builder->add('includeSubs', 'checkbox', array(
			'required' => false,
		));

		$realm_transformer = new RealmTransformer($this->em);
		$builder->add(
			$builder->create('targetRealm', 'text', array(
			'required' => false,
			'attr'=>array('class'=>'realmselect'),
			))->addModelTransformer($realm_transformer)
		);

		$char_transformer = new CharacterTransformer($this->em);
		$builder->add(
			$builder->create('targetCharacter', 'text', array(
			'required' => false,
			'attr'=>array('class'=>'charselect'),
			))->addModelTransformer($char_transformer)
		);

		if ($l == null) {
			$builder->add('submit', 'submit');
		}
	}

	public function getName() {
		return 'listmember';
	}
}
