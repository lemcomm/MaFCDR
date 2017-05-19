<?php

namespace Calitarus\MessagingBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class MessageReplyType extends AbstractType {

	public function getName() {
		return 'message_reply';
	}

	public function setDefaultOptions(OptionsResolverInterface $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'message_reply_9234',
			'translation_domain' => 'MsgBundle',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('topic', 'text', array(
			'required' => false,
			'label' => 'conversation.topic.label',
			'attr' => array('size'=>40, 'maxlength'=>80)
		));

		$builder->add('content', 'textarea', array(
			'label' => 'message.content.label',
			'trim' => true,
			'required' => true
		));

		$builder->add('conversation', 'hidden');
		$builder->add('reply_to', 'hidden');


		$builder->add('submit', 'submit', array('label'=>'message.reply.submit', 'attr'=>array('class'=>'cmsg_button')));
	}

}
