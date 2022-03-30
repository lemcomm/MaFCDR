<?php

namespace BM2\SiteBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;

use BM2\SiteBundle\Entity\AssociationType;

class AssocDeityWordsType extends AbstractType {

	private $deity;

	public function __construct($deity) {
		$this->deity = $deity;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'wordsdeity_1779',
			'translation_domain' => 'orgs'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$builder->add('words', TextareaType::class, array(
			'label'=>'deity.form.new.words',
			'attr' => array('title'=>'deity.help.words'),
			'required'=>false,
			'data'=>$this->deity->getWords()
		));
		$builder->add('submit', SubmitType::class, array('label'=>'deity.form.submit'));
	}

	public function getName() {
		return 'deitywords';
	}
}
