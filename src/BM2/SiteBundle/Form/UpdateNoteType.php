<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class UpdateNoteType extends AbstractType {

	private $note;

	public function __construct($note) {
		$this->note = $note;
	}

	public function setDefaultOptions(OptionsResolverInterface $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'update_42691337',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$note = $this->note;
		$builder->add('version', TextType::class, array(
			'required' => true,
			'label' => 'Version',
			'data' => $note?$note->getVersion():null,
			'attr' => array('size'=>10, 'maxlength'=>20)
		));
		$builder->add('title', TextType::class, array(
			'required' => false,
			'label' => 'Title',
			'data' => $note?$note->getTitle():null,
			'attr' => array('size'=>40)
		));
		$builder->add('text', TextareaType::class, array(
			'label' => 'Update Notes',
			'data' => $note?$note->getText():null,
			'trim' => true,
			'required' => true
		));

		$builder->add('submit', SubmitType::class, array('label'=>'Submit'));
	}

}
