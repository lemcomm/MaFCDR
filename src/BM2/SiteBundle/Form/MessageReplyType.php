<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextAreaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class MessageReplyType extends AbstractType {

	public function setDefaultOptions(OptionsResolverInterface $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'message_reply_9234',
			'translation_domain' => 'conversations',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('type', ChoiceType::class, [
			'label' => "message.content.type",
			'multiple' => false,
			'required' => false,
			'choices' => [
				'letter' => 'type.letter',
				'request' => 'type.request',
				'orders' => 'type.orders',
				'report' => 'type.report',
				'rp' => 'type.rp',
				'ooc' => 'type.ooc'
			],
			'empty_data' => 'letter'
		]);
		$builder->add('content', 'textarea', array(
			'label' => 'message.content.label',
			'trim' => true,
			'required' => true
		));

		$builder->add('conversation', HiddenType::class);
		$builder->add('reply_to', HiddenType::class);

		$builder->add('submit', SubmitType::class, array('label'=>'message.reply.submit', 'attr'=>array('class'=>'cmsg_button')));
	}

}
