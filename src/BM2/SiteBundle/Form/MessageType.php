<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;


class MessageType extends AbstractType {

	private $em;
	private $towers;
	private $reachable_realms;
	private $my_realms;
	private $my_groups;

	public function __construct(EntityManager $em, $towers, $reachable_realms, $my_realms, $my_groups) {
		$this->em = $em;
		$this->towers = $towers;
		$this->reachable_realms = $reachable_realms;
		$this->my_realms = $my_realms;
		$this->my_groups = $my_groups;
	}


	public function configureOptions(OptionsResolverInterface $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'message_24641',
			'attr'					=> array('class'=>'wide')
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('tower', 'entity', array(
			'placeholder' => 'form.choose',
			'label' => 'message.tower',
			'attr' => array('class'=>'tt', 'title'=>'message.tower2'),
			'required' => true,
			'class'=>'BM2SiteBundle:Settlement', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) {
				$qb = $er->createQueryBuilder('c');
				$qb->where('c IN (:towers)');
				$qb->setParameter('towers', $this->towers);
				return $qb;
			},
		));

		if (!empty($this->my_realms)) {
			$builder->add('broadcast_realm', 'entity', array(
				'placeholder' => 'message.nobroadcast',
				'label' => 'message.broadcast',
				'attr' => array('class'=>'tt', 'title'=>'message.broadcast2'),
				'required' => false,
				'class'=>'BM2SiteBundle:Realm', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) {
					$qb = $er->createQueryBuilder('c');
					$qb->where('c IN (:realms)');
					$qb->setParameter('realms', $this->my_realms);
					return $qb;
				},
			));
		}

		if (!empty($this->reachable_realms)) {
			$builder->add('seal_realm', 'entity', array(
				'placeholder' => 'message.nobody',
				'label' => 'message.seal.realm',
				'attr' => array('class'=>'tt', 'title'=>'message.seal.realm2'),
				'required' => false,
				'class'=>'BM2SiteBundle:Realm', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) {
					$qb = $er->createQueryBuilder('c');
					$qb->where('c IN (:realms)');
					$qb->setParameter('realms', $this->reachable_realms);
					return $qb;
				},
			));
		}
		if (!empty($this->my_groups)) {
			$builder->add('seal_group', 'entity', array(
				'placeholder' => 'message.nobody',
				'label' => 'message.seal.group',
				'attr' => array('class'=>'tt', 'title'=>'message.group2'),
				'required' => false,
				'class'=>'BM2SiteBundle:MessageGroup', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) {
					$qb = $er->createQueryBuilder('c');
					$qb->where('c IN (:groups)');
					$qb->setParameter('groups', $this->my_groups);
					return $qb;
				},
			));
		}


		$char_transformer = new CharacterTransformer($this->em);
		$builder->add(
			$builder->create('seal_character', 'text', array(
			'required' => false,
			'label' => 'message.seal.character',
			'attr'=>array('class'=>'charselect tt', 'title'=>'message.seal.character2'),
			))->addModelTransformer($char_transformer)
		);


		$builder->add('content', 'textarea', array(
			'label'=>'message.content',
			'required'=>true,
		));
		$builder->add('tags', 'text', array(
			'label'=>'message.tags',
			'attr' => array('class'=>'tt', 'title'=>'message.tags2'),
			'required'=>false,
			'attr' => array('size'=>80, 'maxlength'=>240)
		));
		$builder->add('lifetime', 'choice', array(
			'label'=>'message.lifetime',
			'attr' => array('class'=>'tt', 'title'=>'message.lifetime2'),
			'required'=>true,
			'multiple'=>false,
			'expanded'=>false,
			'choice_translation_domain' => true,
			'choices'=>array(30=>'message.life.30', 60=>'message.life.60', 90=>'message.life.90', 120=>'message.life.120', 180=>'message.life.180', 360=>'message.life.360')
		));

		$builder->add('submit', 'submit', array('label'=>'message.submit'));
	}

	public function getName() {
		return 'message';
	}
}
