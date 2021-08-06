<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\Settlement;


class SettlementOccupationPermissionsType extends AbstractType {

	private $settlement;
	private $me;
	private $em;
	private $lord;

	public function __construct(Settlement $settlement, Character $me, EntityManager $em, $lord) {
		$this->settlement = $settlement;
		$this->me = $me;
		$this->em = $em;
		$this->lord = $lord;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'settlementpermissions_68956351',
			'translation_domain' => 'politics',
			'data_class'		=> 'BM2\SiteBundle\Entity\SettlementPermission',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$lord = $this->lord;
		$s = $this->settlement;
		$builder->add('occupied_settlement', 'entity', array(
			'required' => true,
			'class'=>'BM2SiteBundle:Settlement', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($s) {
				return $er->createQueryBuilder('s')->where('s = :s')->setParameter('s',$s);
			}
		));
		// TODO: filter according to what's available? (e.g. no permission for docks at regions with no coast)
		$builder->add('permission', 'entity', array(
			'required' => true,
			'choice_translation_domain' => true,
			'class'=>'BM2SiteBundle:Permission',
			'choice_label'=>'translation_string',
			'query_builder'=>function(EntityRepository $er) {
				return $er->createQueryBuilder('p')->where('p.class = :class')->setParameter('class', 'settlement');
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
		return 'settlementpermissions';
	}
}
