<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityManager;

use BM2\SiteBundle\Entity\Character;

class RealmPermissionsSetType extends AbstractType {

	private $me;
	private $em;

	public function __construct(Character $me, EntityManager $em) {
		$this->me = $me;
		$this->em = $em;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'rps_13371337',
			'data_class'		=> 'BM2\SiteBundle\Entity\Realm',
			'translation_domain' => 'politics'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('permissions', 'collection', array(
			'type'		=> new RealmPermissionsType($builder->getData(), $this->me, $this->em),
			'allow_add'	=> true,
			'allow_delete' => true,
			'cascade_validation' => true
		));
	}

	public function getName() {
		return 'rps';
	}
}
