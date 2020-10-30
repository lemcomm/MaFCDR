<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use BM2\SiteBundle\Entity\PlaceType;
use BM2\SiteBundle\Entity\Place;

class PlaceManageType extends AbstractType {

	private $description;
	private $type;
	private $me;

	public function __construct($description, $type, Place $me) {
		$this->description = $description;
		$this->type = $type;
		$this->me = $me;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'manageplace_1947',
			'translation_domain' => 'places'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$me = $this->me;
		$type = $me->getType()->getName();
		$name = $me->getName();
		$formal = $me->getFormalName();
		$short = $me->getShortDescription();
		$description = $this->description;

		$builder->add('name', 'text', array(
			'label'=>'names.name',
			'required'=>true,
			'data'=>$name,
			'attr' => array(
				'size'=>20,
				'maxlength'=>40,
				'title'=>'help.new.name'
			)
		));
		$builder->add('formal_name', 'text', array(
			'label'=>'names.formalname',
			'required'=>true,
			'data'=>$formal,
			'attr' => array(
				'size'=>40,
				'maxlength'=>160,
				'title'=>'help.new.formalname'
			)
		));

		$builder->add('short_description', 'textarea', array(
			'label'=>'description.short',
			'data'=>$short,
			'attr' => array('title'=>'help.new.shortdesc'),
			'required'=>true,
		));
		$builder->add('description', 'textarea', array(
			'label'=>'description.full',
			'attr' => array('title'=>'help.new.longdesc'),
			'data'=>$description,
			'required'=>true,
		));
		if ($type == 'embassy') {
			$builder->add('for_realm', HiddenType::class, [
				'data'=>false
			]);
			$builder->add('allow_spawn', HiddenType::class, [
				'data'=>false
			]);
			if ($me->getOwner()) {
				$builder->add('realm', EntityType::class, [
					'required'=>false,
					'choices'=> $hosting,
					'class'=>'BM2SiteBundle:Realm',
					'choice_label' => 'name',
					'placeholder'=>'manage.realm.empty',
					'label'=>'manage.realm.name'
				]);
			}
			if (!$me->getHostingRealm()) {
				$builder->add('hosting_realm', EntityType::class, [
					'required'=>false,
					'choices'=> $me->getRealm()->findHierarchy(true),
					'class'=>'BM2SiteBundle:Realm',
					'choice_label' => 'name',
					'placeholder'=>'manage.hosting.empty',
					'label'=>'manage.hosting.name'
				]);
				$builder->add('owning_realm', HiddenType::class, [
					'data'=>false
				]);
				$builder->add('ambassador', HiddenType::class, [
					'data'=>false
				]);
			} elseif (!$me->getOwningRealm()) {
				$builder->add('hosting_realm', HiddenType::class, [
					'data'=>$me->getHostingRealm()
				]);
				$builder->add('owning_realm', EntityType::class, [
					'required'=>false,
					'choices'=> $me->getHostingRealm()->findFriendlyRelations(),
					'class'=>'BM2SiteBundle:Realm',
					'choice_label' => 'name',
					'placeholder'=>'manage.hosted.empty',
					'label'=>'manage.hosted.name'
				]);
				$builder->add('ambassador', HiddenType::class, [
					'data'=>false
				]);
			} else {
				$builder->add('owning_realm', HiddenType::class, [
					'data'=>$me->getHostingRealm()
				]);
				$builder->add('hosting_realm', HiddenType::class, [
					'data'=>$me->getHostingRealm()
				]);
				$builder->add('ambassador', EntityType::class, [
					'required'=>false,
					'choices'=>$me->getOwningRealm()->findActiveMembers(),
					'class'=>'BM2SiteBundle:Character',
					'choice_label' => 'name',
					'placeholder'=>'manage.ambassador.empty',
					'label'=>'manage.ambassador.name'
				]);
			}
		}
	}

	public function getName() {
		return 'placemanage';
	}
}
