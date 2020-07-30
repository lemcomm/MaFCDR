<?php

namespace BM2\SiteBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Doctrine\ORM\EntityRepository;


class AddParticipantType extends AbstractType {

	private $recipients;

	public function __construct($recipients) {
		$this->recipients = $recipients;
	}

	public function setDefaultOptions(OptionsResolverInterface $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'new_conversation_134',
			'translation_domain' => 'conversations'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$recipients = $this->recipients;
		$builder->add('contacts', EntityType::class, array(
			'required' => false,
			'multiple'=>true,
			'expanded'=>true,
			'label' => false,
			'placeholder' => 'add.empty',
			'class' => 'BM2SiteBundle:Character',
			'property' => 'name',
			'query_builder'=>function(EntityRepository $er) use ($recipients) {
				$qb = $er->createQueryBuilder('c');
				$qb->where('c IN (:recipients)');
				$qb->orderBy('c.name', 'ASC');
				$qb->setParameter('recipients', $recipients);
				return $qb;
			},
		));

		$builder->add('submit', SubmitType::class, array('label'=>'add.submit', 'attr'=>array('class'=>'cmsg_button')));
	}


}
