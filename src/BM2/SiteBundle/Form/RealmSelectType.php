<?php

namespace BM2\SiteBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class RealmSelectType extends AbstractType {

	private $realms;
	private $empty;
	private $label;
	private $submit;
	private $domain;
	private $msg;

	public function __construct($realms, $type) {
		$this->realms = $realms;
		switch ($type) {
			case 'changerealm':
				$this->empty	= '';
				$this->label	= 'control.changerealm.realm';
				$this->submit	= 'control.changerealm.submit';
				$this->msg      = null;
				$this->domain	= 'actions';
				break;
			case 'take':
				$this->empty	= '';
				$this->label	= 'control.take.realm';
				$this->submit	= 'control.take.submit';
				$this->msg      = null;
				$this->domain	= 'actions';
				break;
			case 'join':
				$this->empty	= 'diplomacy.join.empty';
				$this->label	= 'diplomacy.join.label';
				$this->submit	= 'diplomacy.join.submit';
				$this->msg      = 'diplomacy.join.msg';
				$this->domain	= 'politics';
				break;
			case 'changeoccupier':
				$this->empty	= '';
				$this->label	= 'control.changeoccupier.realm';
				$this->submit	= 'control.changeoccupier.submit';
				$this->msg      = null;
				$this->domain	= 'actions';
				break;
			case 'occupy':
				$this->empty	= '';
				$this->label	= 'control.occupy.realm';
				$this->submit	= 'control.occupy.submit';
				$this->msg      = null;
				$this->domain	= 'actions';
				break;
		}
	}

	public function getName() {
		return 'realm';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'realm_9012356',
			'translation_domain' => $this->domain
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$realms = $this->realms;
		$msg = $this->msg;
		// FIXME: for some stupid fucking reason, this doesn't work via pure $realms below the way CharacterSelect works
		$bloodystupidunnecessarynonsense = array();
		foreach ($realms as $fuckingcrap) {
			$bloodystupidunnecessarynonsense[] = $fuckingcrap->getId();
		}

		$builder->add('target', EntityType::class, array(
			'placeholder' => $this->empty,
			'label' => $this->label,
			'class'=>'BM2SiteBundle:Realm', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($bloodystupidunnecessarynonsense) {
				$qb = $er->createQueryBuilder('r');
				$qb->where('r IN (:realms)');
				$qb->setParameter('realms', $bloodystupidunnecessarynonsense);
				return $qb;
			},
		));
		if ($msg !== null) {
			$builder->add('message', TextareaType::class, [
				'label' => $msg,
				'translation_domain'=>'politics',
				'required' => true
			]);
		}

		$builder->add('submit', SubmitType::class, array('label'=>$this->submit));
	}


}
