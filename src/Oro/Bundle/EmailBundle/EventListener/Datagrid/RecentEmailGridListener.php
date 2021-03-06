<?php

namespace Oro\Bundle\EmailBundle\EventListener\Datagrid;

use Oro\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource;
use Oro\Bundle\DataGridBundle\Event\BuildAfter;
use Oro\Bundle\EmailBundle\Datagrid\EmailGridHelper;
use Oro\Bundle\EmailBundle\Datagrid\EmailQueryFactory;

class RecentEmailGridListener
{
    /** @var EmailGridHelper */
    protected $emailGridHelper;

    /** @var  EmailQueryFactory */
    protected $emailQueryFactory;

    /**
     * @param EmailGridHelper        $emailGridHelper
     * @param EmailQueryFactory|null $emailQueryFactory
     */
    public function __construct(EmailGridHelper $emailGridHelper, EmailQueryFactory $emailQueryFactory = null)
    {
        $this->emailGridHelper   = $emailGridHelper;
        $this->emailQueryFactory = $emailQueryFactory;
    }

    /**
     * @param BuildAfter $event
     */
    public function onBuildAfter(BuildAfter $event)
    {
        $datagrid   = $event->getDatagrid();
        $datasource = $datagrid->getDatasource();
        if ($datasource instanceof OrmDatasource) {
            $parameters = $datagrid->getParameters();
            $userId = $parameters->get('userId');
            $queryBuilder = $datasource->getQueryBuilder();

            $this->emailQueryFactory->applyAcl($queryBuilder);
            $this->emailQueryFactory->prepareQuery($queryBuilder);

            // bind 'origin_ids' parameter
            $originIds    = [];
            $emailOrigins = $this->emailGridHelper->getEmailOrigins($userId);
            foreach ($emailOrigins as $emailOrigin) {
                $originIds[] = $emailOrigin->getId();
            }
            $queryBuilder->setParameter('origin_ids', $originIds);
        }
    }
}
