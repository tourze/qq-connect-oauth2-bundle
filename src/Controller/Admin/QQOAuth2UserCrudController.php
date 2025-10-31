<?php

declare(strict_types=1);

namespace Tourze\QQConnectOAuth2Bundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;

#[AdminCrud(
    routePath: '/qq-oauth2/user',
    routeName: 'qq_oauth2_user'
)]
final class QQOAuth2UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return QQOAuth2User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('QQ OAuth2 用户')
            ->setEntityLabelInPlural('QQ OAuth2 用户管理')
            ->setPageTitle(Crud::PAGE_INDEX, 'QQ OAuth2 用户列表')
            ->setPageTitle(Crud::PAGE_NEW, '创建 QQ OAuth2 用户')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑 QQ OAuth2 用户')
            ->setPageTitle(Crud::PAGE_DETAIL, 'QQ OAuth2 用户详情')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setSearchFields(['openid', 'unionid', 'nickname', 'userReference'])
            ->showEntityActionsInlined()
            ->setFormThemes(['@EasyAdmin/crud/form_theme.html.twig'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')->onlyOnIndex();
        yield TextField::new('openid', 'QQ OpenID')
            ->setRequired(true)
            ->setHelp('QQ用户的唯一标识符')
        ;
        yield TextField::new('unionid', 'QQ UnionID')
            ->setRequired(false)
            ->setHelp('QQ用户的统一标识符')
            ->hideOnIndex()
        ;
        yield TextField::new('nickname', '昵称')
            ->setRequired(false)
            ->setHelp('QQ用户昵称')
        ;
        yield UrlField::new('avatar', '头像地址')
            ->setRequired(false)
            ->setHelp('QQ用户头像URL')
            ->hideOnIndex()
        ;
        yield TextField::new('gender', '性别')
            ->setRequired(false)
            ->setHelp('用户性别')
            ->hideOnIndex()
        ;
        yield TextField::new('province', '省份')
            ->setRequired(false)
            ->setHelp('用户所在省份')
            ->hideOnIndex()
        ;
        yield TextField::new('city', '城市')
            ->setRequired(false)
            ->setHelp('用户所在城市')
            ->hideOnIndex()
        ;
        yield AssociationField::new('config', '关联配置')
            ->setRequired(true)
            ->autocomplete()
            ->setHelp('关联的QQ OAuth2配置')
        ;
        yield TextField::new('userReference', '用户引用')
            ->setRequired(false)
            ->setHelp('关联的本地用户标识')
            ->hideOnIndex()
        ;
        yield TextareaField::new('accessToken', '访问令牌')
            ->setRequired(true)
            ->setHelp('QQ API访问令牌')
            ->hideOnIndex()
        ;
        yield TextareaField::new('refreshToken', '刷新令牌')
            ->setRequired(false)
            ->setHelp('用于刷新访问令牌的令牌')
            ->hideOnIndex()
        ;
        yield IntegerField::new('expiresIn', '过期时间（秒）')
            ->setRequired(true)
            ->setHelp('令牌过期时间')
            ->hideOnIndex()
        ;
        yield DateTimeField::new('tokenUpdateTime', '令牌更新时间')
            ->setRequired(true)
            ->setHelp('令牌最后更新时间')
        ;
        yield CodeEditorField::new('rawData', '原始数据')
            ->setLanguage('javascript')
            ->hideOnIndex()
            ->setHelp('从QQ API获取的原始用户数据')
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
            ->add(TextFilter::new('openid', 'QQ OpenID'))
            ->add(TextFilter::new('unionid', 'QQ UnionID'))
            ->add(TextFilter::new('nickname', '昵称'))
            ->add(TextFilter::new('userReference', '用户引用'))
            ->add(EntityFilter::new('config', '关联配置'))
            ->add(DateTimeFilter::new('tokenUpdateTime', '令牌更新时间'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }
}
