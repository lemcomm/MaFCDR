<?php

namespace BM2\SiteBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use BM2\SiteBundle\Entity\Character;
use BM2\SiteBundle\Entity\PlaceType;
use BM2\SiteBundle\Entity\Place;

class PlaceManageType extends AbstractType {

	private $description;
	private $type;
	private $me;
	private $char;

	public function __construct($description, $type, Place $me, Character $char) {
		$this->description = $description;
		$this->type = $type;
		$this->me = $me;
		$this->char = $char;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'manageplace_1947',
			'translation_domain' => 'places'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$me = $this->me;
		$char = $this->char;
		$type = $me->getType()->getName();
		$name = $me->getName();
		$formal = $me->getFormalName();
		$short = $me->getShortDescription();
		$description = $this->description;

		$builder->add('name', TextType::class, array(
			'label'=>'names.name',
			'required'=>true,
			'data'=>$name,
			'attr' => array(
				'size'=>20,
				'maxlength'=>40,
				'title'=>'help.new.name'
			)
		));
		$builder->add('formal_name', TextType::class, array(
			'label'=>'names.formalname',
			'required'=>true,
			'data'=>$formal,
			'attr' => array(
				'size'=>40,
				'maxlength'=>160,
				'title'=>'help.new.formalname'
			)
		));

		$builder->add('short_description', TextareaType::class, array(
			'label'=>'description.short',
			'data'=>$short,
			'attr' => array('title'=>'help.new.shortdesc'),
			'required'=>true,
		));
		$builder->add('description', TextareaType::class, array(
			'label'=>'description.full',
			'attr' => array('title'=>'help.new.longdesc'),
			'data'=>$description,
			'required'=>true,
		));
		if ($type == 'embassy') {
			$builder->add('for_realm', HiddenType::class, [
				'data'=>false
			]);
			if ($me->getOwner() === $char) {
				$builder->add('realm', EntityType::class, [
					'required'=>false,
					'choices'=> $me->getOwner()->findRealms(),
					'class'=>'BM2SiteBundle:Realm',
					'choice_label' => 'name',
					'placeholder'=>'realm.empty',
					'label'=>'realm.label',
					'data'=>$me->getRealm()
				]);
			}
			if (!$me->getHostingRealm()) {
				$builder->add('hosting_realm', EntityType::class, [
					'required'=>false,
					'choices'=> $me->getRealm()->findHierarchy(true),
					'class'=>'BM2SiteBundle:Realm',
					'choice_label' => 'name',
					'placeholder'=>'realm.empty',
					'label'=>'hosting.label',
					'data'=>$me->getHostingRealm()
				]);
				$builder->add('owning_realm', HiddenType::class, [
					'data'=>null
				]);
				$builder->add('ambassador', HiddenType::class, [
					'data'=>null
				]);
			} elseif (!$me->getOwningRealm()) {
				$builder->add('hosting_realm', EntityType::class, [
					'required'=>false,
					'choices'=> $me->getRealm()->findHierarchy(true),
					'class'=>'BM2SiteBundle:Realm',
					'choice_label' => 'name',
					'data'=>$me->getHostingRealm(),
					'placeholder'=>'realm.empty',
					'label'=>'hosting.label'
				]);
				$builder->add('owning_realm', EntityType::class, [
					'required'=>false,
					'choices'=> $me->getHostingRealm()->findFriendlyRelations(),
					'class'=>'BM2SiteBundle:Realm',
					'choice_label' => 'name',
					'placeholder'=>'realm.empty',
					'label'=>'owning.label',
					'data'=>$me->getOwningRealm()
				]);
				$builder->add('ambassador', HiddenType::class, [
					'data'=>null
				]);
			} else {
				$builder->add('hosting_realm', EntityType::class, [
					'required'=>false,
					'choices'=> $me->getRealm()->findHierarchy(true),
					'class'=>'BM2SiteBundle:Realm',
					'choice_label' => 'name',
					'data'=>$me->getHostingRealm(),
					'placeholder'=>'realm.empty',
					'label'=>'hosting.label'
				]);
				$builder->add('owning_realm', EntityType::class, [
					'required'=>false,
					'choices'=> $me->getHostingRealm()->findFriendlyRelations(),
					'class'=>'BM2SiteBundle:Realm',
					'choice_label' => 'name',
					'placeholder'=>'realm.empty',
					'label'=>'owning.label',
					'data'=>$me->getOwningRealm()
				]);
				$builder->add('ambassador', EntityType::class, [
					'required'=>false,
					'choices'=>$me->getOwningRealm()->findActiveMembers(),
					'class'=>'BM2SiteBundle:Character',
					'choice_label' => 'name',
					'placeholder'=>'ambassador.empty',
					'label'=>'ambassador.label',
					'data'=>$me->getAmbassador()
				]);
			}
		} else {
			if ($me->getOwner()) {
				$builder->add('realm', EntityType::class, [
					'required'=>false,
					'choices'=> $me->getOwner()->findRealms(),
					'class'=>'BM2SiteBundle:Realm',
					'choice_label' => 'name',
					'placeholder'=>'realm.empty',
					'label'=>'realm.label',
					'data'=>$me->getRealm()
				]);
			}
		}
	}

	public function getName() {
		return 'placemanage';
	}
}
