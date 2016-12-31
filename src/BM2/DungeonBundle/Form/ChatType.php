<?php

namespace BM2\DungeonBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;


class ChatType extends AbstractType {

	public function getName() {
		return 'chat';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'chat_14',
			'data_class'			=> 'BM2\DungeonBundle\Entity\DungeonMessage',
			'translation_domain' => 'dungeons'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('content', 'text', array(
			'label' => false,
			'required' => true,
			'max_length' => 200,
			'attr' => array('placeholder'=>'dungeon.chat.hint')
		));

		$builder->add('submit', 'submit', array('label'=>'dungeon.chat.submit'));
	}

}
