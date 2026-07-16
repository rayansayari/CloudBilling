<?php

namespace App\Form;

use App\Entity\Company;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CompanyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la société',
                'attr' => ['placeholder' => 'Ex: Tech Solutions']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email de contact',
                'required' => false,
                'attr' => ['placeholder' => 'contact@techsolutions.com']
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => ['placeholder' => '+216 12 345 678']
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
                'attr' => ['placeholder' => '123 Rue de la République']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description / Informations',
                'required' => false,
                'attr' => ['rows' => 4, 'placeholder' => 'Informations supplémentaires sur le client...']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Company::class,
        ]);
    }
}
