<?php
/**
 * LINK Mobility SMS Notifications
 *
 * Sends transactional SMS notifications through the LINK Mobility messaging
 * service.
 *
 * @package Linkmobility\Notifications\Plugin\Component\MassAction
 * @author Joseph Leedy <joseph@wagento.com>
 * @author Yair García Torres <yair.garcia@wagento.com>
 * @copyright Copyright (c) LINK Mobility (https://www.linkmobility.com/)
 * @license https://opensource.org/licenses/OSL-3.0.php Open Software License 3.0
 */
declare(strict_types=1);

namespace Linkmobility\Notifications\Plugin\Component\MassAction;

use Linkmobility\Notifications\Api\ConfigInterface;
use Linkmobility\Notifications\Model\ResourceModel\SmsSubscription\Collection as SmsSubscriptionCollection;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;

/**
 * Plug-in for Mass Action Filter UI Component
 *
 * Replaces core logic to filter by SMS notification type instead of entity ID.
 *
 * @package Linkmobility\Notifications\Plugin\Component\MassAction
 * @author Joseph Leedy <joseph@wagento.com>
 * @see \Magento\Ui\Component\MassAction\Filter
 */
class FilterPlugin
{
    /**
     * @var \Magento\Ui\Component\MassAction\Filter
     */
    private $filter;
    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $request;
    /**
     * @var \Magento\Framework\Api\FilterBuilder
     */
    private $filterBuilder;
    /**
     * @var \Magento\Framework\View\Element\UiComponent\DataProvider\DataProviderInterface
     */
    private $dataProvider;
    /**
     * @var \Linkmobility\Notifications\Api\ConfigInterface
     */
    private $config;

    public function __construct(
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        ConfigInterface $config
    ) {
        $this->request = $request;
        $this->filterBuilder = $filterBuilder;
        $this->config = $config;
    }

    /**
     * @param \Magento\Ui\Component\MassAction\Filter $subject
     * @param callable $proceed
     * @param \Magento\Framework\Data\Collection\AbstractDb $collection
     * @return \Magento\Framework\Data\Collection\AbstractDb
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function aroundGetCollection(
        Filter $subject,
        callable $proceed,
        AbstractDb $collection
    ) {
        if (!$this->isModuleEnabled() || !($collection instanceof SmsSubscriptionCollection)) {
            return $proceed($collection);
        }

        $this->filter = $subject;
        $selected = $this->request->getParam($subject::SELECTED_PARAM);
        $excluded = $this->request->getParam($subject::EXCLUDED_PARAM);
        $isExcludedIdsValid = is_array($excluded) && count($excluded) > 0;
        $isSelectedIdsValid = is_array($selected) && count($selected) > 0;

        if ($excluded !== 'false') {
            if (!$isExcludedIdsValid && !$isSelectedIdsValid) {
                throw new LocalizedException(
                    __('An SMS notification type must be selected. Please choose at least one and try again.')
                );
            }
        }

        $this->initDataProvider();
        $this->applySelectionOnTargetProvider();

        $collection->addFieldToFilter('sms_type', ['in' => $this->getSmsTypes()]);

        return $collection;
    }

    private function isModuleEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function initDataProvider(): void
    {
        if ($this->dataProvider !== null) {
            return;
        }

        $component = $this->filter->getComponent();

        $this->filter->prepareComponent($component);

        $this->dataProvider = $component->getContext()->getDataProvider();
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function applySelectionOnTargetProvider(): void
    {
        $selected = $this->request->getParam($this->filter::SELECTED_PARAM);
        $excluded = $this->request->getParam($this->filter::EXCLUDED_PARAM);

        if ($excluded === 'false') {
            return;
        }

        try {
            if (is_array($excluded) && count($excluded) > 0) {
                $this->filterBuilder->setConditionType('nin')
                    ->setField('sms_type')
                    ->setValue($excluded);

                $this->dataProvider->addFilter($this->filterBuilder->create());
            } elseif (is_array($selected) && count($selected) > 0) {
                $this->filterBuilder->setConditionType('in')
                    ->setField('sms_type')
                    ->setValue($selected);

                $this->dataProvider->addFilter($this->filterBuilder->create());
            }
        } catch (\Exception $e) {
            throw new LocalizedException(__($e->getMessage()));
        }
    }

    /**
     * @return string[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function getSmsTypes(): array
    {
        return $this->dataProvider->getAllSmsTypes();
    }
}