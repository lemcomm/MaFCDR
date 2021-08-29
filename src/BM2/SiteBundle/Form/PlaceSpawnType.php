<?php

namespace BM2\SiteBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use BM2\SiteBundle\Entity\PlaceType;

class PlaceSpawnType extends AbstractType {

	private $house;
	private $realms;

	public function __construct($realms, $house) {
		$this->house = $house;
		$this->realms = $realms;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'newplace_1337',
			'translation_domain' => 'places'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
                $builder->add('realm', EntityType::class, array(
                        'label'=>'spawn.form.realm',
                        'required'=>false,
                        'placeholder' => 'spawn.form.empty',
                        'attr' => array('title'=>'help.spawn.realm'),
                        'class' => 'BM2SiteBundle:Realm',
                        'choice_translation_domain' => true,
                        'choice_label' => 'name',
                        'choices' => $realms
                ));
                if ($this->house) {
                        $houses = $this->house->finAllSuperiors(true);
        		$builder->add('house', EntityType::class, array(
        			'label'=>'spawn.form.house',
        			'required'=>false,
        			'placeholder' => 'spawn.form.empty',
        			'attr' => array('title'=>'help.spawn.house'),
        			'class' => 'BM2SiteBundle:House',
        			'choice_translation_domain' => true,
        			'choice_label' => 'name',
        			'choices' => $houses
        		));
                } else {
                        $builder->add('house', HiddenType::class);
                }
		$builder->add('submit', SubmitType::class, array(
                        'label'=>'button.submit',
                        'translation_domain'=>'settings',));
	}

	public function getName() {
		return 'placespawn';
	}
}
