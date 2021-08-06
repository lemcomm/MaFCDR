<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityManager;

use BM2\SiteBundle\Entity\Character;


class SettlementPermissionsSetType extends AbstractType {

	private $me;
	private $em;
	private $lord;

	public function __construct(Character $me, EntityManager $em, $lord) {
		$this->me = $me;
		$this->em = $em;
		$this->lord = $lord;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'sps_41312',
			'data_class'		=> 'BM2\SiteBundle\Entity\Settlement',
			'translation_domain' => 'actions'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$lord = $this->lord;
		if ($lord) {
			$builder->add('allow_spawn', 'checkbox', array(
				'label' => "control.permissions.spawn",
				'required' => false,
			));

			$builder->add('allow_thralls', 'checkbox', array(
				'label' => "control.permissions.thralls",
				'required' => false,
			));

			$builder->add('feed_soldiers', 'checkbox', array(
				'label' => "control.permissions.feedsoldiers",
				'required' => false,
			));

			$builder->add('permissions', 'collection', array(
				'type'		=> new SettlementPermissionsType($builder->getData(), $this->me, $this->em, $lord),
				'allow_add'	=> true,
				'allow_delete' => true,
				'cascade_validation' => true
			));
		} else {
			$builder->add('occupation_permissions', 'collection', array(
				'type'		=> new SettlementOccupationPermissionsType($builder->getData(), $this->me, $this->em, $lord),
				'allow_add'	=> true,
				'allow_delete' => true,
				'cascade_validation' => true
			));
		}
	}

	public function getName() {
		return 'sps';
	}
}
