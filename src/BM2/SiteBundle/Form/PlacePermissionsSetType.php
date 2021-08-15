<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityManager;

use BM2\SiteBundle\Entity\Character;

class PlacePermissionsSetType extends AbstractType {

	private $me;
	private $em;
	private $owner;

	public function __construct(Character $me, EntityManager $em, $owner) {
		$this->me = $me;
		$this->em = $em;
		$this->owner = $owner;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'pps_499912',
			'data_class'		=> 'BM2\SiteBundle\Entity\Place',
			'translation_domain' => 'places'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$owner = $this->owner;
		if ($owner) {
			if ($p->getType()->getPublic() === false) {
				$builder->add('public', 'checkbox', [
					'required'=> false,
					'label'=> 'control.place.public',
				]);
			}

			$builder->add('permissions', 'collection', array(
				'type'		=> new PlacePermissionsType($builder->getData(), $this->me, $this->em),
				'allow_add'	=> true,
				'allow_delete' => true,
				'cascade_validation' => true
			));
		} else {
			$builder->add('permissions', 'collection', array(
				'type'		=> new PlaceOccupationPermissionsType($builder->getData(), $this->me, $this->em),
				'allow_add'	=> true,
				'allow_delete' => true,
				'cascade_validation' => true
			));
		}
	}

	public function getName() {
		return 'pps';
	}
}
