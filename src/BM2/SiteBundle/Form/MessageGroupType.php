<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Doctrine\ORM\EntityRepository;


class MessageGroupType extends AbstractType {

	public function getName() {
		return 'messagegroup';
	}

	public function configureOptions(OptionsResolverInterface $resolver) {
		$resolver->setDefaults(array(
			'intention'     		=> 'messagegroup_2974',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('name', 'text', array(
			'label'=>'message.group.name',
			'required'=>true,
			'empty_data'=>'(unnamed)',
			'attr' => array('size'=>20, 'maxlength'=>60)
		));
		$builder->add('open', 'checkbox', array(
			'required' => false,
			'label' => 'message.group.open',
			'attr' => array('title'=>'message.group.open2'),
		));

		$builder->add('submit', 'submit', array('label'=>'message.group.submit'));

	}


}
