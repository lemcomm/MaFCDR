<?php

namespace BM2\SiteBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


class NewsArticleType extends AbstractType {

	public function getName() {
		return 'newsarticle';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'newsarticle_2461',
			'data_class'		=> 'BM2\SiteBundle\Entity\NewsArticle',
			'translation_domain' => 'communication'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('edition', 'hidden_entity', array(
			'required'=>true,
			'entity_repository'=>'BM2SiteBundle:NewsEdition'
		));
		$builder->add('title', 'text', array(
			'label'=>'news.article.title',
			'required' => true,
			'attr' => array('size'=>40, 'maxlength'=>80)
		));
		$builder->add('content', 'textarea', array(
			'label'=>'news.article.content',
			'trim'=>true,
			'required'=>true
		));

		$builder->add('submit', 'submit', array('label'=>'news.article.create'));
	}


}
