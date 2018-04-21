<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Realm;

class RealmPermissionsType extends AbstractType {

	private $place;
	private $me;
	private $em;

	public function __construct(Realm $realm, Character $me, EntityManager $em) {
		$this->realm = $realm;
		$this->me = $me;
		$this->em = $em;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'placepermissions_68351',
			'translation_domain' => 'politics',
			'data_class'		=> 'BM2\SiteBundle\Entity\RealmPermission',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$r = $this->realm;
		$builder->add('place', 'entity', array(
			'required' => true,
			'class'=>'BM2SiteBundle:Realm', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($r) {
				return $er->createQueryBuilder('r')->where('r = :r')->setParameter('r',$r);
			}
		));
		// TODO: filter according to what's available? (e.g. no permission for docks at regions with no coast)
		$builder->add('permission', 'entity', array(
			'required' => true,
			'choice_translation_domain' => true,
			'class'=>'BM2SiteBundle:Permission',
			'choice_label'=>'translation_string',
			'query_builder'=>function(EntityRepository $er) {
				return $er->createQueryBuilder('p')->where('p.class = :class')->setParameter('class', 'realm');
			}
		));
		$builder->add('value', 'integer', array(
			'required' => false,
		));
		$builder->add('reserve', 'integer', array(
			'required' => false,
		));

		$me = $this->me;
		$builder->add('listing', 'entity', array(
			'required' => true,
			'placeholder'=>'perm.choose',
			'class'=>'BM2SiteBundle:Listing', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($me) {
				return $er->createQueryBuilder('l')->where('l.owner = :me')->setParameter('me',$me->getUser());
			}
		));
	}

	public function getName() {
		return 'realmpermissions';
	}
}
