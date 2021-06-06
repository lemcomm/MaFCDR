<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use BM2\SiteBundle\Entity\EquipmentType;

class CharacterLoadoutType extends AbstractType {

        private $weapons;
        private $armor;
        private $equipment;
        private $mounts;

        public function __construct($data) {
                $this->weapons = $data['wpns'];
                $this->armor = $data['arms'];
                $this->equipment = $data['othr'];
                $this->mounts = $data['mnts'];
        }

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'characterloadout_19',
			'translation_domain' => 'settings',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
                $builder->add('weapon', EntityType::class, array(
                        'label'=>'loadout.weapon',
                        'placeholder'=>'loadout.none',
                        'required'=>True,
                        'choice_label'=>'nameTrans',
                        'choice_translation_domain' => 'messages',
                        'class'=>EquipmentType::class,
                        'choices'=>$this->weapons
                ));
                $builder->add('armour', EntityType::class, array(
                        'label'=>'loadout.armor',
                        'placeholder'=>'loadout.none',
                        'required'=>True,
                        'choice_label'=>'nameTrans',
                        'choice_translation_domain' => 'messages',
                        'class'=>EquipmentType::class,
                        'choices'=>$this->armor
                ));
                $builder->add('equipment', EntityType::class, array(
                        'label'=>'loadout.equipment',
                        'placeholder'=>'loadout.none',
                        'required'=>True,
                        'choice_label'=>'nameTrans',
                        'choice_translation_domain' => 'messages',
                        'class'=>EquipmentType::class,
                        'choices'=>$this->equipment
                ));
                $builder->add('mount', EntityType::class, array(
                        'label'=>'loadout.mount',
                        'placeholder'=>'loadout.none',
                        'required'=>True,
                        'choice_label'=>'nameTrans',
                        'choice_translation_domain' => 'messages',
                        'class'=>EquipmentType::class,
                        'choices'=>$this->mounts
                ));

		$builder->add('submit', SubmitType::class, array('label'=>'submit'));
	}

	public function getName() {
		return 'characterloadout';
	}

}
