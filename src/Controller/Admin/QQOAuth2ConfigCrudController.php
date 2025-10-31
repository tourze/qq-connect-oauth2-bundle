<?php

declare(strict_types=1);

namespace Tourze\QQConnectOAuth2Bundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;

#[AdminCrud(
    routePath: '/qq-oauth2/config',
    routeName: 'qq_oauth2_config'
)]
final class QQOAuth2ConfigCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return QQOAuth2Config::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('QQ OAuth2 配置')
            ->setEntityLabelInPlural('QQ OAuth2 配置管理')
            ->setPageTitle(Crud::PAGE_INDEX, 'QQ OAuth2 配置列表')
            ->setPageTitle(Crud::PAGE_NEW, '创建 QQ OAuth2 配置')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑 QQ OAuth2 配置')
            ->setPageTitle(Crud::PAGE_DETAIL, 'QQ OAuth2 配置详情')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setSearchFields(['appId', 'scope'])
            ->showEntityActionsInlined()
            ->setFormThemes(['@EasyAdmin/crud/form_theme.html.twig'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->onlyOnIndex();
        yield TextField::new('appId', 'QQ应用ID')
            ->setRequired(true)
            ->setHelp('从QQ开放平台获取的应用ID')
        ;
        yield TextField::new('appSecret', 'QQ应用密钥')
            ->setRequired(true)
            ->setHelp('从QQ开放平台获取的应用密钥')
            ->hideOnIndex()
        ;
        yield TextareaField::new('scope', '授权范围')
            ->setRequired(false)
            ->setHelp('OAuth授权范围，多个用逗号分隔，如：get_user_info,list_album')
            ->hideOnIndex()
        ;
        yield BooleanField::new('valid', '是否启用')
            ->renderAsSwitch(false)
            ->setHelp('是否启用此配置')
        ;
        yield DateTimeField::new('createTime', '创建时间')
            ->hideOnForm()
        ;
        yield DateTimeField::new('updateTime', '更新时间')
            ->hideOnForm()
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('appId', 'QQ应用ID'))
            ->add(BooleanFilter::new('valid', '是否启用'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
            ->add(DateTimeFilter::new('updateTime', '更新时间'))
        ;
    }
}
