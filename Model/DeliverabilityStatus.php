<?php
/**
 * See LICENSE.md for license details.
 */
declare(strict_types=1);

namespace PostDirekt\Addressfactory\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\ResourceModel\GridInterface;
use Psr\Log\LoggerInterface;

/**
 * Class DeliverabilityStatus
 *
 * @author   Andreas Müller <andreas.mueller@netresearch.de>
 * @link     https://www.netresearch.de/
 */
class DeliverabilityStatus
{
    private const NOT_ANALYSED = 'not_analysed';
    private const PENDING = 'pending';
    private const UNDELIVERABLE = 'undeliverable';
    private const POSSIBLY_DELIVERABLE = 'possibly_deliverable';
    private const DELIVERABLE = 'deliverable';
    private const ADDRESS_CORRECTED = 'address_corrected';
    private const ANALYSIS_FAILED = 'analysis_failed';

    /**
     * @var AnalysisStatusRepository
     */
    private $repository;

    /**
     * @var AnalysisStatusFactory
     */
    private $statusFactory;

    /**
     * @var GridInterface
     */
    private $orderGrid;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        AnalysisStatusRepository $repository,
        AnalysisStatusFactory $analysisStatusFactory,
        GridInterface $oderGrid,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->repository = $repository;
        $this->statusFactory = $analysisStatusFactory;
        $this->orderGrid = $oderGrid;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Update analysis status in persistent storage and sales order grid.
     *
     * @param AnalysisStatus $status
     * @return bool
     */
    private function updateStatus(AnalysisStatus $status): bool
    {
        try {
            $this->repository->save($status);
            // if asynchronous grid indexing is disabled, grid data must be refreshed explicitly.
            if (!$this->scopeConfig->getValue('dev/grid/async_indexing')) {
                $this->orderGrid->refresh($status->getOrderId());
            }
        } catch (CouldNotSaveException $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return false;
        }

        return true;
    }

    public function setStatusPending(OrderInterface $order): bool
    {
        $analysisStatus = $this->statusFactory->create();
        $analysisStatus->setData([
            AnalysisStatus::ORDER_ID => $order->getEntityId(),
            AnalysisStatus::STATUS => self::PENDING
        ]);

        return $this->updateStatus($analysisStatus);
    }

    public function setStatusUndeliverable(OrderInterface $order): bool
    {
        $analysisStatus = $this->statusFactory->create();
        $analysisStatus->setData([
            AnalysisStatus::ORDER_ID => $order->getEntityId(),
            AnalysisStatus::STATUS => self::UNDELIVERABLE
        ]);

        return $this->updateStatus($analysisStatus);
    }

    public function setStatusPossiblyDeliverable(OrderInterface $order): bool
    {
        $analysisStatus = $this->statusFactory->create();
        $analysisStatus->setData([
            AnalysisStatus::ORDER_ID => $order->getEntityId(),
            AnalysisStatus::STATUS => self::POSSIBLY_DELIVERABLE
        ]);

        return $this->updateStatus($analysisStatus);
    }

    public function setStatusDeliverable(OrderInterface $order): bool
    {
        $analysisStatus = $this->statusFactory->create();
        $analysisStatus->setData([
            AnalysisStatus::ORDER_ID => $order->getEntityId(),
            AnalysisStatus::STATUS => self::DELIVERABLE
        ]);

        return $this->updateStatus($analysisStatus);
    }

    public function setStatusAddressCorrected(OrderInterface $order): bool
    {
        $analysisStatus = $this->statusFactory->create();
        $analysisStatus->setData([
            AnalysisStatus::ORDER_ID => $order->getEntityId(),
            AnalysisStatus::STATUS => self::ADDRESS_CORRECTED
        ]);

        return $this->updateStatus($analysisStatus);
    }

    public function setStatusAnalysisFailed(OrderInterface $order): bool
    {
        $analysisStatus = $this->statusFactory->create();
        $analysisStatus->setData([
            AnalysisStatus::ORDER_ID => $order->getEntityId(),
            AnalysisStatus::STATUS => self::ANALYSIS_FAILED
        ]);

        return $this->updateStatus($analysisStatus);
    }

    public function getStatus(OrderInterface $order): string
    {
        try {
            return $this->repository->getByOrderId($order->getEntityId())->getStatus();
        } catch (NoSuchEntityException $exception) {
            return self::NOT_ANALYSED;
        }
    }
}