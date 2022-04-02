<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class JournalType extends AbstractType {

	public function setDefaultOptions(OptionsResolverInterface $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'journal_22020329',
			'translation_domain' => 'messages',
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('topic', TextType::class, array(
			'required' => true,
			'label' => 'journal.form.topic.label',
			'attr' => array('size'=>40, 'maxlength'=>80)
		));
		$builder->add('entry', TextareaType::class, array(
			'label' => 'journal.form.entry.label',
			'trim' => true,
			'required' => true
		));
		$builder->add('public', CheckboxType::class, array(
			'label'=>'journal.form.public.label',
			'required'=>false,
			'data'=>false,
		));
		$builder->add('ooc', CheckboxType::class, array(
			'label'=>'journal.form.ooc.label',
			'required'=>false,
			'data'=>false,
		));
		$builder->add('graphic', CheckboxType::class, array(
			'label'=>'journal.form.graphic.label',
			'required'=>false,
			'data'=>false,
		));

		$builder->add('submit', SubmitType::class, array('label'=>'journal.form.submit'));
	}

}
