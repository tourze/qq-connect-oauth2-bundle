<?php

namespace Tourze\QQConnectOAuth2Bundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2Config;
use Tourze\QQConnectOAuth2Bundle\Entity\QQOAuth2User;

/**
 * @extends ServiceEntityRepository<QQOAuth2User>
 *
 * @method QQOAuth2User|null find($id, $lockMode = null, $lockVersion = null)
 * @method QQOAuth2User|null findOneBy(array $criteria, array $orderBy = null)
 * @method QQOAuth2User[]    findAll()
 * @method QQOAuth2User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class QQOAuth2UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QQOAuth2User::class);
    }

    public function findByUnionid(string $unionid): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.unionid = :unionid')
            ->setParameter('unionid', $unionid)
            ->getQuery()
            ->getResult();
    }

    public function findByUserReference(string $userReference): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.userReference = :userReference')
            ->setParameter('userReference', $userReference)
            ->getQuery()
            ->getResult();
    }

    public function updateOrCreate(array $data, QQOAuth2Config $config): QQOAuth2User
    {
        $user = $this->findByOpenid($data['openid']);

        if (!$user) {
            $user = new QQOAuth2User(
                $data['openid'],
                $data['access_token'],
                $data['expires_in'],
                $config
            );
        } else {
            $user->setAccessToken($data['access_token'])
                ->setExpiresIn($data['expires_in']);
        }

        if (isset($data['refresh_token'])) {
            $user->setRefreshToken($data['refresh_token']);
        }

        if (isset($data['unionid'])) {
            $user->setUnionid($data['unionid']);
        }

        if (isset($data['nickname'])) {
            $user->setNickname($data['nickname']);
        }

        if (isset($data['figureurl_qq_2']) || isset($data['figureurl_qq_1'])) {
            $avatar = $data['figureurl_qq_2'] ?? $data['figureurl_qq_1'] ?? null;
            if ($avatar) {
                $user->setAvatar($avatar);
            }
        }

        if (isset($data['gender'])) {
            $user->setGender($data['gender']);
        }

        if (isset($data['province'])) {
            $user->setProvince($data['province']);
        }

        if (isset($data['city'])) {
            $user->setCity($data['city']);
        }

        $user->setRawData($data);

        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();

        return $user;
    }

    public function findByOpenid(string $openid): ?QQOAuth2User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.openid = :openid')
            ->setParameter('openid', $openid)
            ->getQuery()
            ->getOneOrNullResult();
    }
}