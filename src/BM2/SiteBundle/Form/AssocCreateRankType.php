<?php

namespace BM2\SiteBundle\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;

use BM2\SiteBundle\Entity\AssociationType;

class AssocCreateRankType extends AbstractType {

	private $ranks;

	public function __construct($ranks) {
		$this->ranks = $ranks;
	}

	public function configureOptions(OptionsResolver $resolver) {
		$resolver->setDefaults(array(
			'intention'       	=> 'newassocrank_13349',
			'translation_domain' => 'orgs'
		));
	}

	public function buildForm(FormBuilderInterface $builder, array $options) {
		$ranks = $this->ranks;
		#TODO: ALL OF THIS.
		$builder->add('name', TextType::class, array(
			'label'=>'assoc.form.createRank.name',
			'required'=>true,
			'attr' => array(
				'size'=>20,
				'maxlength'=>40,
				'title'=>'assoc.help.newRank.name'
			)
		));
		$builder->add('description', TextareaType::class, array(
			'label'=>'assoc.form.description.full',
			'attr' => array('title'=>'assoc.help.longdesc'),
			'required'=>true,
		));
		$builder->add('viewAll', CheckboxType::class, array(
			'label'=>'assoc.form.createRank.viewAll',
			'attr' => array('title'=>'assoc.help.viewAll'),
			'required' => true
		));
		$builder->add('viewUp', IntegerType::class, array(
			'label'=>'assoc.form.createRank.viewUp',
			'attr' => array('title'=>'assoc.help.viewUp'),
			'empty_data' => 1,
			'constraints' => [
				new GreaterThan([
					'value' => -1,
				]),
			],
		));
		$builder->add('viewDown', IntegerType::class, array(
			'label'=>'assoc.form.createRank.viewDown',
			'attr' => array('title'=>'assoc.help.viewDown'),
			'empty_data' => 1,
			'constraints' => [
				new GreaterThan([
					'value' => -1,
				]),
			],
		));
		$builder->add('viewSelf', CheckboxType::class, array(
			'label'=>'assoc.form.createRank.public',
			'attr' => array('title'=>'assoc.help.public'),
			'required' => true
		));
		$builder->add('superior', EntityType::class, array(
			'label'=>'assoc.form.createRank.type',
			'required'=>false,
			'placeholder' => 'assoc.form.createRank.selectsuperior',
			'attr' => array('title'=>'assoc.help.superior'),
			'class' => 'BM2SiteBundle:AssociationRank',
			'choice_translation_domain' => true,
			'choice_label' => 'name',
			'choices' => $ranks
		));
		$builder->add('createSubs', CheckboxType::class, array(
			'label'=>'assoc.form.createRank.public',
			'attr' => array('title'=>'assoc.help.createSubs'),
			'required' => true
		));
		$builder->add('manager', CheckboxType::class, array(
			'label'=>'assoc.form.createRank.short',
			'attr' => array('title'=>'assoc.help.manager'),
			'required'=>true,
		));
		$builder->add('submit', SubmitType::class, array('label'=>'assoc.form.submit'));
	}

	public function getName() {
		return 'assoccreaterank';
	}
}
