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

class AssocDeityUpdateType extends AbstractType {

	private $deity;
	private $aspects;

	public function __construct($deity, $aspects) {
		$this->deity = $deity;
		$this->aspects = $aspects;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'updatedeity_1779',
			'translation_domain' => 'orgs'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$aspects = $this->aspects;
		$deity = $this->deity;
		$deityAspects = [];
		foreach ($deity->getAspects() as $each) {
			$deityAspects[] = $each->getAspect();
		}
		$builder->add('name', TextType::class, array(
			'label'=>'deity.form.new.name',
			'required'=>true,
			'attr' => array(
				'size'=>20,
				'maxlength'=>40,
				'title'=>'deity.help.name'
			),
			'data'=>$deity->getName(),
		));
		$builder->add('aspects', EntityType::class, array(
			'label'=>'deity.form.new.aspects',
			'required'=>true,
			'attr' => array('title'=>'deity.help.aspects'),
			'class' => 'BM2SiteBundle:AspectType',
			'choice_translation_domain' => true,
			'choice_label' => 'name',
			'multiple' => true,
			'expanded' => false,
			'choices' => $aspects,
			'data'=>$deityAspects,
		));
		$builder->add('description', TextareaType::class, array(
			'label'=>'deity.form.description.full',
			'attr' => array('title'=>'deity.help.desc'),
			'required'=>true,
			'data'=>$deity->getDescription()->getText()
		));
		$builder->add('submit', SubmitType::class, array('label'=>'deity.form.submit'));
	}

	public function getName() {
		return 'deityupdate';
	}
}
