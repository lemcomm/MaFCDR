<?php

namespace BM2\SiteBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityRepository;


class AssocSelectType extends AbstractType {

	private $assocs;
	private $empty;
	private $label;
	private $submit;
	private $domain;
	private $msg;
	private $help;
	private $me;

	public function __construct($assocs, $type, $me = null) {
		$this->assocs = $assocs;
		switch ($type) {
			case 'faith':
				$this->empty	= 'assoc.form.faith.empty';
				$this->label	= 'assoc.form.faith.name';
				$this->submit	= 'assoc.form.submit';
				$this->msg      = null;
				$this->domain	= 'orgs';
				$this->help	= 'assoc.help.faith';
				break;
		}
		$this->me = $me;
	}

	public function getName() {
		return 'assoc';
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       => 'realm_9012356',
			'translation_domain' => $this->domain
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {

		$builder->add('target', EntityType::class, array(
			'placeholder' => $this->empty,
			'label' => $this->label,
			'required'=>false,
			'attr' => array('title'=>$this->help),
			'class'=>'BM2SiteBundle:Association',
			'choice_label'=>'faith_name',
			'choices'=>$this->assocs,
			'data'=>$this->me->getFaith()
		));
		if ($this->msg !== null) {
			$builder->add('message', TextareaType::class, [
				'label' => $this->msg,
				'translation_domain'=>$this->domain,
				'required' => true
			]);
		}

		$builder->add('submit', SubmitType::class, array('label'=>$this->submit));
	}


}
