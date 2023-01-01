<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use BM2\SiteBundle\Entity\EquipmentType;

class EquipmentLoadoutType extends AbstractType {

        private $opts;
        private $label;
        private $domain;

        public function __construct($opts, $label, $domain) {
                $this->opts = $opts;
                $this->label = $label;
                $this->domain = $domain;
        }

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'equipmentselect4321',
			'translation_domain' => $this->domain,
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
                $builder->add('equipment', EntityType::class, array(
                        'label'=>$this->label,
                        'placeholder'=>'loadout.none',
                        'required'=>true,
                        'choice_label'=>'nameTrans',
                        'choice_translation_domain' => 'messages',
                        'class'=>EquipmentType::class,
                        'choices'=>$this->opts
                ));

		$builder->add('submit', SubmitType::class, array('label'=>'button.submit'));
	}

	public function getName() {
		return 'eqpmntsel';
	}

}
