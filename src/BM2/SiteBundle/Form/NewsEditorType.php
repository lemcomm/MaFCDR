<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use BM2\SiteBundle\Entity\NewsPaper;


class NewsEditorType extends AbstractType {

	private $paper;

	public function __construct(NewsPaper $paper) {
		$this->paper = $paper;
	}

	public function getName() {
		return 'newseditor';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'newseditor_93245',
			'translation_domain' => 'communication'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$paper = $this->paper;

		$builder->add('owner', 'checkbox', array(
			'required'=>false,
			'label'=>'news.owner',
			'attr' => array('title'=>'news.help.owner')
		));
		$builder->add('editor', 'checkbox', array(
			'required'=>false,
			'label'=>'news.editor',
			'attr' => array('title'=>'news.help.editor')
		));
		$builder->add('author', 'checkbox', array(
			'required'=>false,
			'label'=>'news.author',
			'attr' => array('title'=>'news.help.author')
		));
		$builder->add('publisher', 'checkbox', array(
			'required'=>false,
			'label'=>'news.publisher',
			'attr' => array('title'=>'news.help.publisher')
		));
	}


}
