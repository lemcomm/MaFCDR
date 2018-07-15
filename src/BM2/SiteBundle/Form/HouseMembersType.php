<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class HouseMembersType extends AbstractType {

	private $members;
	private $notinclude;

	public function __construct($members, $notinclude = true) {
		$this->members = array();
		foreach ($members as $member) {
			$this->members[] = $member->getId();
		}
		$this->notinclude = $notinclude;
	}


	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'housemembers_8675309',
			'translation_domain' => 'politics',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$members = $this->members;
		$notinclude = $this->notinclude;

		$builder->add('member', 'entity', array(
			'label'=>'house.members.member',
			'required' => true,
			'multiple' => false,
			'expanded' => false,
			'class'=>'BM2SiteBundle:Character',
			'choice_label'=>'name',
			'group_by' => function($val, $key, $index) {
				return $val->getHouse()->getName();
			},
			'query_builder'=>function(EntityRepository $er) use ($members) {
				return $er->createQueryBuilder('c')->where('c in (:all)')->setParameter('all', $members)->orderBy('c.name', 'ASC');
			}
		));

		if ($notinclude) {
			$builder->add('submit', 'submit', array('label'=>'house.members.submit'));
		}
	}

	public function getName() {
		return 'housemembers';
	}
}
