<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class QuestType extends AbstractType {

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'quest_7523',
			'translation_domain' => 'actions',
			'data_class'			=> 'BM2\SiteBundle\Entity\Quest',
			'attr'					=> array('class'=>'wide')
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('summary', 'text', array(
			'label'=>'quests.summary',
			'required'=>true,
			'attr' => array('size'=>80, 'maxlength'=>240)
		));
		$builder->add('description', 'textarea', array(
			'label'=>'quests.desc',
			'required'=>true,
		));
		$builder->add('reward', 'textarea', array(
			'label'=>'quests.reward',
			'required'=>true,
		));

		$builder->add('submit', 'submit', array('label'=>'quests.submit'));
	}

	public function getName() {
		return 'quest';
	}
}
