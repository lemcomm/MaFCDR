<?php

namespace BM2\SiteBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Doctrine\ORM\EntityRepository;

class SoldierFoodType extends AbstractType {

	private $settlements;

	public function __construct($settlements) {
		$this->settlements = $settlements;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'	=> 'soldierfood_1998',
			'translation_domain' => 'actions'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$settlements = $this->settlements;

		if (date("m") == 12) {
			$year = date("Y")+1;
			$month = '1';
		} else {
			$year = date("Y");
			$month = date("m")+1;
		}
		$day = date("d");
		$hour = date("h");
		$minute = date("i");

		$builder->add('subject', TextType::class, array(
			'label' => 'request.generic.subject',
			'required' => true,
			'attr' => array('title'=>'request.generic.help.subject')
		));
		$builder->add('text', TextareaType::class, array(
			'label' => 'request.generic.text',
			'required' => true,
			'attr' => array('title'=>'request.generic.help.text')
		));
		$builder->add('target', EntityType::class, array(
			'label' => 'request.soldierfood.estate',
			'class'=>'BM2SiteBundle:Settlement',
			'choice_label'=>'name',
			'choices'=>$settlements,
			'query_builder'=>function(EntityRepository $er) use ($settlements) {
				$qb = $er->createQueryBuilder('s');
				$qb->where('s in :settlements')->setParameter('settlements', $settlements)->orderBy('s.realm.name', 'ASC')->addOrderBy('s.name');
				return $qb;
				},
			#'query_builder'=>function(EntityRepository $er) use ($settlements, $char) {
			#	$qb = $er->createQueryBuilder('s');
			#	$qb->join('s.realm', 'r')->where('s.realm IN (:realms)')->andWhere('s.owner != :char')->setParameters(array('realms'=>$realms, 'char'=>$char));
			#	$qb->orderBy('r.name')->addOrderBy('s.name');
			#	return $qb;
			#},
			'group_by' => function($val, $key, $index) {
				return $val->getRealm()->getName();
			},
			'attr' => array('title'=>'request.soldierfood.estatehelp')
		));
		$builder->add('limit', NumberType::class, array(
			'label' => 'request.soldierfood.limit',
			'attr' => array('title'=>'request.soldierfood.limithelp'),
			'required' => false
		));


		$builder->add('expires', DateTimeType::class, array(
			'attr' => array('title'=>'request.generic.help.expires'),
			'required' => false,
			'placeholder' => array('year' => 'request.generic.year', 'month'=> 'request.generic.month', 'day'=>'request.generic.day', 'hour'=>'request.generic.hour', 'minute'=>'request.generic.minute'),
			'years' => array(date("Y"), date("Y")+1, date("Y")+2)
		));

		$builder->add('submit', 'submit', array('label'=>'request.generic.submit'));
	}

	public function getName() {
		return 'soldierfood';
	}
}
