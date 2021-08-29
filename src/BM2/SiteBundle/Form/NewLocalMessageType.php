<?php

namespace BM2\SiteBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Doctrine\ORM\EntityRepository;


class NewLocalMessageType extends AbstractType {

	private $settlement;
	private $place;
	private $reply;

	public function __construct($settlement, $place, $reply) {
		$this->settlement = $settlement;
		$this->place = $place;
		$this->reply = $reply;
	}

	public function getName() {
		return 'new_local_message';
	}

	public function setDefaultOptions(OptionsResolverInterface $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'new_local_messsage_134',
			'translation_domain' => 'conversations'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$place = $this->place;
		$settlement = $this->settlement;
		if ($this->reply) {
			$reply = 'reply';
		} else {
			$reply = 'new';
		}

		$target = ['local'=>'conversation.target.local'];
		if ($place) {
			$target['place'] = 'conversation.target.place';
		}
		if ($settlement) {
			$target['settlement'] = 'conversation.target.settlement';
		}
		$builder->add('topic', TextType::class, array(
			'required' => true,
			'label' => 'conversation.topic.label',
			'attr' => array('size'=>40, 'maxlength'=>80)
		));
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

		$builder->add('content', TextareaType::class, array(
			'label' => 'message.content.label',
			'trim' => true,
			'required' => true
		));

		$builder->add('target', ChoiceType::class, [
			'required' => true,
			'multiple' => false,
			'expanded' => false,
			'label' => 'conversation.target.label',
			'choices' => $target,
			'placeholder' => 'conversation.target.choose',
		]);
		$builder->add('reply_to', HiddenType::class);

		$builder->add('submit', SubmitType::class, array('label'=>'message.send', 'attr'=>array('class'=>'cmsg_button')));
	}


}
