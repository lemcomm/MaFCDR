<?php

namespace BM2\DungeonBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class TargetSelectType extends AbstractType {

	protected $type;
	protected $choices;
	protected $current;

	public function __construct($type, $choices, $current) {
		$this->type = $type;
		$this->choices = $choices;
		$this->current = $current;
	}

	public function getName() {
		return 'target_'.$this->type;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'targetselect_513',
			'translation_domain' => 'dungeons',
			'attr'					=> array('class'=>'targetselect')
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('type', 'hidden', array('data'=>$this->type));
		$options = array(
			'label' => false,
			'required' => true,
			'choices' => $this->choices
		);
		if ($this->current) {
			$options['data'] = $this->current;
		}
		$builder->add('target', 'choice', $options);
	}

}
