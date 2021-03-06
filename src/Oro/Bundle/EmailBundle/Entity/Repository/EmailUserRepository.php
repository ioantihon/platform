<?php

namespace Oro\Bundle\EmailBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

use Oro\Bundle\EmailBundle\Entity\Email;
use Oro\Bundle\EmailBundle\Entity\EmailFolder;
use Oro\Bundle\EmailBundle\Entity\EmailUser;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\User;

class EmailUserRepository extends EntityRepository
{
    /**
     * @param Email $email
     * @param User $user
     * @param Organization $organisation
     *
     * @return EmailUser[]
     */
    public function findByEmailAndOwner(Email $email, User $user, Organization $organisation)
    {
        return $this->findBy([
            'email' => $email,
            'owner' => $user,
            'organization' => $organisation
        ]);
    }

    /**
     * @param $email Email
     *
     * @return EmailUser[]
     */
    public function findByEmailForMailbox(Email $email)
    {
        return $this->createQueryBuilder('ue')
            ->andWhere('ue.email = :email')
            ->andWhere('ue.mailboxOwner IS NOT NULL')
            ->setParameter('email', $email)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param User $user
     * @param Organization $organization
     * @param array $folderTypes
     * @param bool $isSeen
     * @return array
     */
    public function getEmailUserList(User $user, Organization $organization, array $folderTypes = [], $isSeen = null)
    {
        $qb = $this->createQueryBuilder('eu');
        $qb
            ->join('eu.folder', 'f')
            ->join('f.origin', 'o')
            ->andWhere($qb->expr()->eq('eu.owner', $user->getId()))
            ->andWhere($qb->expr()->eq('eu.organization', $organization->getId()))
            ->andWhere($qb->expr()->eq('o.isActive', ':active'))
            ->setParameter('active', true);

        if ($folderTypes) {
            $qb->andWhere($qb->expr()->in('f.type', $folderTypes));
        }

        if ($isSeen !== null) {
            $qb->andWhere($qb->expr()->eq('eu.seen', ':seen'))
            ->setParameter('seen', (bool)$isSeen);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array       $ids
     * @param EmailFolder $folder
     *
     * @return array
     */
    public function getInvertedIdsFromFolder(array $ids, EmailFolder $folder)
    {
        $qb = $this->createQueryBuilder('email_user');

        $qb->select('email_user.id')
            ->andWhere('email_user.folder = :folder')
            ->setParameter('folder', $folder);

        if ($ids) {
            $qb->andWhere($qb->expr()->notIn('email_user.id', ':ids'))
                ->setParameter('ids', $ids);
        }

        $emailUserIds = $qb->getQuery()->getArrayResult();

        $ids = [];
        foreach ($emailUserIds as $emailUserId) {
            $ids[] = $emailUserId['id'];
        }

        return $ids;
    }

    /**
     * @param array $ids
     * @param bool  $seen
     *
     * @return mixed
     */
    public function setEmailUsersSeen(array $ids, $seen)
    {
        $qb = $this->createQueryBuilder('email_user');

        return $qb->update()->set('email_user.seen', ':seen')
            ->where($qb->expr()->in('email_user.id', ':ids'))
            ->setParameter('seen', $seen)
            ->setParameter('ids', $ids)
            ->getQuery()->execute();
    }

    /**
     * Get all unseen user email
     *
     * @param User $user
     * @param Organization $organization
     * @return mixed
     */
    public function findUnseenUserEmail(User $user, Organization $organization)
    {
        $qb = $this->createQueryBuilder('eu');

        return $qb
            ->andWhere($qb->expr()->eq('eu.owner', ':owner'))
            ->andWhere($qb->expr()->eq('eu.organization', ':organization'))
            ->andWhere($qb->expr()->eq('eu.seen', ':seen'))
            ->setParameter('owner', $user)
            ->setParameter('organization', $organization)
            ->setParameter('seen', false);
    }

    /**
     * @param array $ids
     * @param User $user
     * @param string $folderType
     * @param bool $isAllSelected
     * @return QueryBuilder
     */
    public function getEmailUserBuilderForMassAction($ids, User $user, $folderType, $isAllSelected)
    {
        $queryBuilder = $this->createQueryBuilder('eu');
        $queryBuilder->join('eu.email', 'e');

        $this->applyOwnerFilter($queryBuilder, $user);
        $this->applyHeadFilter($queryBuilder, true);

        if ($folderType) {
            $this->applyFolderFilter($queryBuilder, $folderType);
        }

        if (!$isAllSelected) {
            $this->applyIdFilter($queryBuilder, $ids);
        }

        return $queryBuilder;
    }

    /**
     * @param array $threadIds
     * @param User $user
     * @return QueryBuilder
     */
    public function getEmailUserByThreadId($threadIds, User $user)
    {
        $queryBuilder = $this->createQueryBuilder('eu');
        $queryBuilder->join('eu.email', 'e');
        $queryBuilder->join('e.thread', 't');
        $this->applyOwnerFilter($queryBuilder, $user);
        $this->applyHeadFilter($queryBuilder, false);
        $queryBuilder->andWhere($queryBuilder->expr()->in('t.id', $threadIds));

        return $queryBuilder;
    }

    /**
     * @param EmailFolder $folder
     *
     * @return QueryBuilder
     */
    public function getEmailUserByFolder($folder)
    {
        $queryBuilder = $this->createQueryBuilder('eu')
            ->andWhere('eu.folder = :folder')
            ->setParameter('folder', $folder);

        return $queryBuilder;
    }

    /**
     * @param EmailFolder $folder
     * @param array       $messages
     * @return EmailUser[]
     */
    public function getEmailUsersByFolderAndMessageIds(EmailFolder $folder, array $messages)
    {
        return $this
            ->getEmailUserByFolder($folder)
            ->leftJoin('eu.email', 'email')
            ->andWhere('email.messageId IN (:messageIds)')
            ->setParameter('messageIds', $messages)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param User $user
     * @return $this
     */
    protected function applyOwnerFilter(QueryBuilder $queryBuilder, User $user)
    {
        $queryBuilder->andWhere('eu.owner = ?1');
        $queryBuilder->setParameter(1, $user);

        return $this;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param array $ids
     * @return $this
     */
    protected function applyIdFilter(QueryBuilder $queryBuilder, $ids)
    {
        if ($ids) {
            $queryBuilder->andWhere($queryBuilder->expr()->in('eu.id', $ids));
        }

        return $this;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string $type
     * @return $this
     */
    protected function applyFolderFilter(QueryBuilder $queryBuilder, $type)
    {
        $queryBuilder->join('eu.folder', 'f');
        $queryBuilder->andWhere($queryBuilder->expr()->eq('f.type', $queryBuilder->expr()->literal($type)));

        return $this;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param bool $isHead
     */
    protected function applyHeadFilter(QueryBuilder $queryBuilder, $isHead = true)
    {
        $queryBuilder->add(
            'where',
            $queryBuilder->expr()->andX($queryBuilder->expr()->eq('e.head', ':head'))
        )->setParameters(['head' => (bool)$isHead]);
    }
}
