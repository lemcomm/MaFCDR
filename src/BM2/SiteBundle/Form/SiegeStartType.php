<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

use Symfony\Component\OptionsResolver\OptionsResolver;

class SiegeStartType extends AbstractType {

	private $settlement;
	private $place;
	private $realms;
	private $wars;

	public function __construct($settlement = null, $place = null, $realms = null, $wars = null) {
		$this->settlement = $settlement;
		$this->place = $place;
		$this->realms = $realms;
		$this->wars = $wars;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'	=> 'siegestart_9753',
			'translation_domain' => 'actions'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$settlement = $this->settlement;
		$place = $this->place;
		$wars = $this->wars;
		$realms = $this->realms;

		if ($settlement) {
			$builder->add('settlement', CheckboxType::class, array(
				'required'=>false,
				'label'=> 'military.siege.menu.confirm'
			));
		} else {
			$builder->add('settlement', HiddenType::class, array(
				'data'=>false
			));
		}

		if ($place) {
			$builder->add('place', CheckboxType::class, array(
				'required'=>false,
				'label'=> 'military.siege.place.confirm'
			));
		} else {
			$builder->add('place', HiddenType::class, array(
				'data'=>false
			));
		}

		$builder->add('war', EntityType::class, [
			'required'=>false,
			'choices'=> $wars,
			'class'=>'BM2SiteBundle:War',
			'choice_label' => 'summary',
			'placeholder'=>'military.siege.menu.none',
			'label'=>'military.siege.menu.wars'
		]);
		$builder->add('realm', EntityType::class, [
			'required'=>false,
			'choices'=> $realms,
			'class'=>'BM2SiteBundle:Realm',
			'choice_label' => 'name',
			'placeholder'=>'military.siege.menu.none',
			'label'=>'military.siege.menu.realms'
		]);
		$builder->add('submit', 'submit', array('label'=>'military.siege.submit'));
	}

	public function getName() {
		return 'siegestart';
	}

}
