<?php

declare(strict_types=1);

namespace Tourze\QQConnectOAuth2Bundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2State;

#[AdminCrud(
    routePath: '/qq-oauth2/state',
    routeName: 'qq_oauth2_state'
)]
final class QQOAuth2StateCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return QQOAuth2State::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('QQ OAuth2 状态')
            ->setEntityLabelInPlural('QQ OAuth2 状态管理')
            ->setPageTitle(Crud::PAGE_INDEX, 'QQ OAuth2 状态列表')
            ->setPageTitle(Crud::PAGE_NEW, '创建 QQ OAuth2 状态')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑 QQ OAuth2 状态')
            ->setPageTitle(Crud::PAGE_DETAIL, 'QQ OAuth2 状态详情')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setSearchFields(['state', 'sessionId'])
            ->showEntityActionsInlined()
            ->setFormThemes(['@EasyAdmin/crud/form_theme.html.twig'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->onlyOnIndex();
        yield TextField::new('state', 'OAuth状态值')
            ->setRequired(true)
            ->setHelp('OAuth授权过程中的状态标识符')
        ;
        yield TextField::new('sessionId', '会话ID')
            ->setRequired(false)
            ->setHelp('关联的会话ID')
            ->hideOnIndex()
        ;
        yield AssociationField::new('config', '关联配置')
            ->setRequired(true)
            ->autocomplete()
            ->setHelp('关联的QQ OAuth2配置')
        ;
        yield CodeEditorField::new('metadata', '元数据')
            ->setLanguage('javascript')
            ->hideOnIndex()
            ->setHelp('存储的元数据信息')
        ;
        yield DateTimeField::new('expireTime', '过期时间')
            ->setRequired(true)
            ->setHelp('状态过期时间')
        ;
        yield BooleanField::new('used', '是否已使用')
            ->renderAsSwitch(false)
            ->setHelp('状态是否已被使用')
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
            ->add(TextFilter::new('state', 'OAuth状态值'))
            ->add(TextFilter::new('sessionId', '会话ID'))
            ->add(EntityFilter::new('config', '关联配置'))
            ->add(BooleanFilter::new('used', '是否已使用'))
            ->add(DateTimeFilter::new('expireTime', '过期时间'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }
}
