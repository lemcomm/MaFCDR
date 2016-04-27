<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;

class CharacterCreationType extends AbstractType {

	private $user;
	private $slotsavailable;

	public function __construct($user, $slotsavailable) {
		$this->user = $user;
		$this->slotsavailable = $slotsavailable;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'	=> 'newchar_482',
			'attr'		=> array('class'=>'wide')
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$user = $this->user;
		$builder->add('name', 'text', array(
			'label'=>'character.name',
			'required'=>true,
			'attr' => array('size'=>30, 'maxlength'=>80, 'title'=>'newcharacter.help.name')
		));
		$builder->add('gender', 'choice', array(
			'label'=>'character.gender',
			'required'=>true,
			'choices'=>array('m'=>'male', 'f'=>'female'),
			'attr' => array('title'=>'newcharacter.help.gender'),
			'choice_translation_domain' => true,
		));

		$builder->add('father', 'entity', array(
			'label'=>'character.father',
			'required'=>false,
			'placeholder'=>'character.none',
			'attr' => array('title'=>'newcharacter.help.father'),
			'class'=>'BM2SiteBundle:Character', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($user) {
				return $er->createQueryBuilder('c')
					->leftJoin('c.partnerships', 'm', 'WITH', '(m.with_sex IS NULL OR (m.with_sex=true AND m.active=true))')->leftJoin('m.partners', 'p', 'WITH', 'p.id != c.id')
					->where('(c.user = :user OR p.user = :user)')->andWhere('c.male = true')->andWhere('c.npc = false')->orderBy('c.name')
					->setParameters(array('user'=>$user));
		}));
		$builder->add('mother', 'entity', array(
			'label'=>'character.mother',
			'required'=>false,
			'placeholder'=>'character.none',
			'attr' => array('title'=>'newcharacter.help.mother'),
			'class'=>'BM2SiteBundle:Character', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($user) {
				return $er->createQueryBuilder('c')
					->leftJoin('c.partnerships', 'm', 'WITH', '(m.with_sex IS NULL OR (m.with_sex=true AND m.active=true))')->leftJoin('m.partners', 'p', 'WITH', 'p.id != c.id')
					->where('(c.user = :user OR p.user = :user)')->andWhere('c.male = false')->andWhere('c.npc = false')->orderBy('c.name')
					->setParameters(array('user'=>$user));
		}));

		$builder->add('partner', 'entity', array(
			'label'=>'character.married',
			'required'=>false,
			'placeholder'=>'character.none',
			'attr' => array('title'=>'newcharacter.help.partner'),
			'class'=>'BM2SiteBundle:Character', 'choice_label'=>'name', 'query_builder'=>function(EntityRepository $er) use ($user) {
				return $er->createQueryBuilder('c')->where('c.user = :user')->andWhere('c.npc = false')->orderBy('c.name')->setParameters(array('user'=>$user));
		}));

		if ($this->slotsavailable) {
			$builder->add('dead', 'checkbox', array(
				'label' => 'dead',
				'required' => false,
				'attr' => array('title'=>'newcharacter.help.dead'),
			));
		} else {
			$builder->add('dead', 'checkbox', array(
				'label' => 'dead',
				'required' => true,
				'attr' => array('title'=>'newcharacter.help.dead', 'checked'=>'checked', 'disabled'=>'disabled'),
			));
		}

		$builder->add('submit', 'submit', array('label'=>'newcharacter.submit'));
	}

	public function getName() {
		return 'charactercreation';
	}
}
